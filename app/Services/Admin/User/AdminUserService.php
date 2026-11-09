<?php

declare(strict_types=1);

namespace App\Services\Admin\User;

use App\Enums\UserAccountStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Report;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function paginate(array $filters, bool $includeSensitiveSearch = false): LengthAwarePaginator
    {
        return $this->usersQuery($filters, includeSensitiveSearch: $includeSensitiveSearch)
            ->orderBy(
                ltrim((string) ($filters['sort'] ?? '-created_at'), '-'),
                str_starts_with((string) ($filters['sort'] ?? '-created_at'), '-') ? 'desc' : 'asc',
            )
            ->orderBy('users.id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /**
     * Each summary card preserves the active search and common filters,
     * neutralizing only the dimension represented by that card.
     */
    public function overview(array $filters = [], bool $includeSensitiveSearch = false): array
    {
        $allFilters = $this->usersQuery($filters, includeSensitiveSearch: $includeSensitiveSearch);
        $roleScope = $this->usersQuery($filters, includeRole: false, includeSensitiveSearch: $includeSensitiveSearch);
        $statusScope = $this->usersQuery($filters, includeStatus: false, includeSensitiveSearch: $includeSensitiveSearch);
        $newScope = $this->usersQuery($filters, includePeriod: false, includeSensitiveSearch: $includeSensitiveSearch);

        return [
            'total' => (clone $allFilters)->count('users.id'),
            'clients' => (clone $roleScope)->whereHas('roles', fn (Builder $q) => $q->where('name', 'client'))->count('users.id'),
            'professionals' => (clone $roleScope)->whereHas('roles', fn (Builder $q) => $q->where('name', 'professional'))->count('users.id'),
            'active' => (clone $statusScope)->where('users.account_status', UserAccountStatus::ACTIVE->value)->count('users.id'),
            'suspended' => (clone $statusScope)->where('users.account_status', UserAccountStatus::SUSPENDED->value)->count('users.id'),
            'new' => (clone $newScope)->where('users.created_at', '>=', now()->subDays(29)->startOfDay())->count('users.id'),
        ];
    }

    private function usersQuery(
        array $filters,
        bool $includeRole = true,
        bool $includeStatus = true,
        bool $includePeriod = true,
        bool $includeSensitiveSearch = false,
    ): Builder {
        $query = User::query()
            ->select('users.*')
            ->with([
                'roles',
                'profile' => function (HasOne $profile) use ($includeSensitiveSearch): void {
                    $columns = ['id', 'user_id', 'first_name', 'last_name'];
                    if ($includeSensitiveSearch) {
                        $columns[] = 'phone';
                    }

                    $profile->select($columns);
                },
                'professionalProfile',
            ]);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($search, $includeSensitiveSearch): void {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.id', is_numeric($search) ? '=' : 'like', is_numeric($search) ? (int) $search : "%{$search}%");

                if ($includeSensitiveSearch) {
                    $q->orWhere('users.email', 'like', "%{$search}%");
                }

                $q->orWhereHas('profile', function (Builder $profile) use ($search, $includeSensitiveSearch): void {
                    $profile->where(function (Builder $matches) use ($search, $includeSensitiveSearch): void {
                        if ($includeSensitiveSearch) {
                            $matches->where('phone', 'like', "%{$search}%")
                                ->orWhere('first_name', 'like', "%{$search}%");
                        } else {
                            $matches->where('first_name', 'like', "%{$search}%");
                        }

                        $matches->orWhere('last_name', 'like', "%{$search}%");

                        $nameParts = preg_split('/\\s+/', $search, 2);
                        if (count($nameParts) === 2) {
                            [$firstName, $lastName] = $nameParts;
                            $matches->orWhere(function (Builder $name) use ($firstName, $lastName): void {
                                $name->where('first_name', 'like', "%{$firstName}%")
                                    ->where('last_name', 'like', "%{$lastName}%");
                            })->orWhere(function (Builder $name) use ($firstName, $lastName): void {
                                $name->where('first_name', 'like', "%{$lastName}%")
                                    ->where('last_name', 'like', "%{$firstName}%");
                            });
                        }
                    });
                });
            });
        }

        if ($includeRole && !empty($filters['role'])) {
            $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $filters['role']));
        }

        if ($includeStatus && !empty($filters['account_status'])) {
            $query->where('users.account_status', $filters['account_status']);
        }

        if ($includePeriod && !empty($filters['period'])) {
            $start = match ($filters['period']) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(6)->startOfDay(),
                '30d' => now()->subDays(29)->startOfDay(),
            };
            $query->where('users.created_at', '>=', $start);
        }

        if (!empty($filters['created_from'])) {
            $query->whereDate('users.created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->whereDate('users.created_at', '<=', $filters['created_to']);
        }

        if (!empty($filters['location'])) {
            $location = trim((string) $filters['location']);
            $query->where(function (Builder $q) use ($location, $includeSensitiveSearch): void {
                $q->whereHas('professionalProfile', fn (Builder $profile) => $profile
                    ->where('city', 'like', "%{$location}%")
                    ->orWhere('province', 'like', "%{$location}%")
                    ->orWhere('commune', 'like', "%{$location}%"));

                if ($includeSensitiveSearch) {
                    $q->orWhereHas('addresses', fn (Builder $address) => $address
                        ->where('city', 'like', "%{$location}%")
                        ->orWhere('province', 'like', "%{$location}%")
                        ->orWhere('commune', 'like', "%{$location}%")
                        ->orWhere('neighborhood', 'like', "%{$location}%"));
                }
            });
        }

        if (array_key_exists('email_verified', $filters) && $filters['email_verified'] !== null) {
            $filters['email_verified']
                ? $query->whereNotNull('users.email_verified_at')
                : $query->whereNull('users.email_verified_at');
        }

        if (!empty($filters['verification_status'])) {
            $query->whereHas('professionalProfile', fn (Builder $profile) => $profile
                ->where('verification_status', $filters['verification_status']));
        }

        if (!empty($filters['activity']) && $this->sessionActivityAvailable()) {
            $threshold = now()->subDays($filters['activity'] === 'recent_7d' ? 7 : 30)->timestamp;
            if ($filters['activity'] === 'recent_7d') {
                $query->whereExists(fn ($sessions) => $sessions->selectRaw('1')
                    ->from((string) config('session.table', 'sessions'))
                    ->whereColumn(config('session.table', 'sessions').'.user_id', 'users.id')
                    ->where(config('session.table', 'sessions').'.last_activity', '>=', $threshold));
            } else {
                $query->whereNotExists(fn ($sessions) => $sessions->selectRaw('1')
                    ->from((string) config('session.table', 'sessions'))
                    ->whereColumn(config('session.table', 'sessions').'.user_id', 'users.id')
                    ->where(config('session.table', 'sessions').'.last_activity', '>=', $threshold));
            }
        }

        if ($this->sessionActivityAvailable()) {
            $query->addSelect([
                'last_activity_timestamp' => DB::table((string) config('session.table', 'sessions'))
                    ->selectRaw('MAX(last_activity)')
                    ->whereColumn(config('session.table', 'sessions').'.user_id', 'users.id'),
            ]);
        }

        return $query;
    }

    public function sessionActivityAvailable(): bool
    {
        return config('session.driver') === 'database'
            && Schema::hasTable((string) config('session.table', 'sessions'));
    }

    public function detail(User $user, bool $includeSensitiveData = false): array
    {
        $professionalProfile = $user->professionalProfile;
        $professionalId = $professionalProfile?->getKey();
        $clientOrders = Order::query()->where('client_id', $user->getKey());
        $professionalOrders = $professionalId
            ? Order::query()->where('professional_id', $professionalId)
            : Order::query()->whereRaw('1 = 0');

        $defaultAddress = $includeSensitiveData
            ? $user->addresses()->where('is_default', true)->first(['province', 'city', 'commune', 'neighborhood'])
            : null;

        $businessActivity = collect();

        if ($user->hasRole('client')) {
            ServiceRequest::query()->where('client_id', $user->getKey())
                ->latest('created_at')->limit(10)->get(['id', 'title', 'created_at'])
                ->each(fn ($request) => $businessActivity->push([
                    'type' => 'Demande de service',
                    'description' => $request->title ?: 'Demande #'.$request->id,
                    'date' => $request->created_at,
                ]));

            (clone $clientOrders)->latest('created_at')->limit(10)->get(['id', 'order_number', 'status', 'created_at', 'completed_at'])
                ->each(fn ($order) => $businessActivity->push([
                    'type' => $order->completed_at ? 'Commande terminée' : 'Commande',
                    'description' => $order->order_number ?: 'Commande #'.$order->id,
                    'date' => $order->completed_at ?? $order->created_at,
                ]));

            Quotation::query()->whereHas('serviceRequest', fn (Builder $q) => $q->where('client_id', $user->getKey()))
                ->latest('created_at')->limit(10)->get(['id', 'created_at'])
                ->each(fn ($quotation) => $businessActivity->push([
                    'type' => 'Devis reçu',
                    'description' => 'Devis #'.$quotation->id,
                    'date' => $quotation->created_at,
                ]));

            Review::query()->where('client_id', $user->getKey())
                ->latest('created_at')->limit(10)->get(['id', 'published_at', 'created_at'])
                ->each(fn ($review) => $businessActivity->push([
                    'type' => 'Avis créé',
                    'description' => 'Avis #'.$review->id,
                    'date' => $review->published_at ?? $review->created_at,
                ]));
        }

        if ($professionalId !== null) {
            $professionalProfile->serviceRequests()
                ->latest('created_at')->limit(10)->get(['id', 'title', 'created_at'])
                ->each(fn ($request) => $businessActivity->push([
                    'type' => 'Demande reçue',
                    'description' => $request->title ?: 'Demande #'.$request->id,
                    'date' => $request->created_at,
                ]));

            (clone $professionalOrders)->latest('created_at')->limit(10)->get(['id', 'order_number', 'status', 'created_at', 'completed_at'])
                ->each(fn ($order) => $businessActivity->push([
                    'type' => $order->completed_at ? 'Prestation terminée' : 'Commande reçue',
                    'description' => $order->order_number ?: 'Commande #'.$order->id,
                    'date' => $order->completed_at ?? $order->created_at,
                ]));
        }

        $businessActivity = $businessActivity
            ->filter(fn (array $event) => $event['date'] !== null)
            ->sortByDesc(fn (array $event) => $event['date']->getTimestamp())
            ->take(20)
            ->values();

        $lastActivity = null;
        if ($this->sessionActivityAvailable()) {
            $lastActivityTimestamp = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->max('last_activity');
            $lastActivity = $lastActivityTimestamp ? now()->setTimestamp((int) $lastActivityTimestamp) : null;
        }

        return [
            'client' => [
                'requests' => $user->serviceRequests()->count(),
                'quotations' => Quotation::query()->whereHas('serviceRequest', fn (Builder $q) => $q->where('client_id', $user->getKey()))->count(),
                'orders' => (clone $clientOrders)->count(),
                'completed_orders' => (clone $clientOrders)->where('status', 'completed')->count(),
                'cancelled_orders' => (clone $clientOrders)->where('status', 'cancelled')->count(),
                'disputes' => (clone $clientOrders)->where('status', 'disputed')->count(),
                'location' => collect([$defaultAddress?->city, $defaultAddress?->commune, $defaultAddress?->neighborhood, $defaultAddress?->province])->filter()->join(', '),
            ],
            'professional' => $professionalId ? [
                'verification' => $professionalProfile->verification_status?->value,
                'profile_complete' => filled($professionalProfile->professional_title)
                    && filled($professionalProfile->description)
                    && filled($professionalProfile->city)
                    && filled($professionalProfile->province)
                    && $professionalProfile->skills()->exists(),
                'services' => $professionalProfile->services()->count(),
                'published_services' => $professionalProfile->services()->where('status', 'published')->count(),
                'requests' => $professionalProfile->serviceRequests()->count(),
                'quotations' => Quotation::query()->whereHas('serviceRequest', fn (Builder $q) => $q->where('professional_id', $professionalId))->count(),
                'orders' => (clone $professionalOrders)->count(),
                'rating' => $professionalProfile->rating_average,
                'reviews' => $professionalProfile->rating_count,
                'availability' => $professionalProfile->availability_status?->value,
                'status' => $professionalProfile->status,
                'visibility' => $professionalProfile->visibility,
            ] : null,
            'support' => [
                'tickets' => $user->supportTickets()->count(),
                'reports_filed' => Report::query()->where('reporter_id', $user->getKey())->count(),
                'reports_received' => Report::query()
                    ->where('target_type', $user->getMorphClass())
                    ->where('target_id', $user->getKey())
                    ->count(),
            ],
            'last_activity' => $lastActivity,
            'business_activity' => $businessActivity,
            'administrative_activity' => AuditLog::query()
                ->with('user:id,name')
                ->where('subject_type', $user->getMorphClass())
                ->where('subject_id', $user->getKey())
                ->latest('created_at')
                ->latest('id')
                ->limit(20)
                ->get(),
        ];
    }

    public function updateIdentity(User $user, User $actor, array $data, Request $request): User
    {
        $updated = DB::transaction(function () use ($user, $actor, $data, $request): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->getKey() === $actor->getKey() || $target->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'user' => ['Ce compte ne peut pas être modifié depuis cette rubrique.'],
                ]);
            }

            $firstName = trim($data['first_name']);
            $lastName = trim($data['last_name']);
            $fullName = trim($firstName.' '.$lastName);
            $profile = $target->profile()->firstOrNew();

            $previous = [
                'account_name' => $target->name,
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
            ];

            if (
                $target->name === $fullName
                && $profile->first_name === $firstName
                && $profile->last_name === $lastName
            ) {
                throw ValidationException::withMessages([
                    'first_name' => ['Aucune modification du nom n’a été détectée.'],
                ]);
            }

            $profile->first_name = $firstName;
            $profile->last_name = $lastName;
            $profile->save();

            $target->name = $fullName;
            $target->save();

            $this->auditLogService->record(
                'admin.user.identity_updated',
                $target,
                $actor,
                [
                    'previous' => $previous,
                    'new' => [
                        'account_name' => $fullName,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                    ],
                    'reason' => $data['reason'],
                ],
                $request,
            );

            return $target->fresh(['roles', 'profile']);
        });

        $updated->notify(new AccountActivityNotification(
            'Informations de compte corrigées',
            'L’administration a corrigé le prénom ou le nom associé à votre compte PROXIWORK. Si cette modification vous semble incorrecte, contactez le support.',
            'account_identity_updated',
        ));

        return $updated;
    }

    public function suspend(User $user, User $actor, array $data, Request $request): User
    {
        $until = match ($data['duration']) {
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
            'custom' => Carbon::parse($data['suspended_until']),
            default => null,
        };

        $updated = DB::transaction(function () use ($user, $actor, $request, $data, $until): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->getKey() === $actor->getKey() || $target->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'user' => ['Ce compte ne peut pas être suspendu depuis cette rubrique.'],
                ]);
            }

            if ($target->account_status !== UserAccountStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'user' => ['Ce compte est déjà suspendu.'],
                ]);
            }

            $target->account_status = UserAccountStatus::SUSPENDED;
            $target->suspended_at = now();
            $target->suspended_until = $until;
            $target->suspended_by = $actor->getKey();
            $target->suspension_reason = $data['reason_code'];
            $target->suspension_note = $data['note'] ?? null;
            $target->save();

            $target->tokens()->delete();

            if ($this->sessionActivityAvailable()) {
                DB::table((string) config('session.table', 'sessions'))
                    ->where('user_id', $target->getKey())
                    ->delete();
            }

            $this->auditLogService->record(
                'admin.user.suspended',
                $target,
                $actor,
                [
                    'previous_status' => UserAccountStatus::ACTIVE->value,
                    'new_status' => UserAccountStatus::SUSPENDED->value,
                    'reason_code' => $data['reason_code'],
                    'duration' => $data['duration'],
                    'suspended_until' => $until?->toISOString(),
                    'note' => $data['note'] ?? null,
                ],
                $request,
            );

            return $target->fresh(['roles', 'suspendedBy']);
        });

        $reasonLabel = match ($data['reason_code']) {
            'suspected_fraud' => 'Fraude présumée',
            'abusive_use' => 'Utilisation abusive',
            'compromised_account' => 'Compte compromis ou suspect',
            'identity_misrepresentation' => 'Usurpation ou fausses informations',
            'policy_violation' => 'Non-respect des règles de la plateforme',
            'administrative_decision' => 'Décision administrative motivée',
            default => 'Autre motif administratif',
        };
        $endLabel = $until?->format('d/m/Y à H:i') ?? 'jusqu’à nouvel examen';

        $updated->notify(new AccountActivityNotification(
            'Compte suspendu',
            "Votre compte PROXIWORK a été suspendu par l’administration. Motif : {$reasonLabel}. Fin prévue : {$endLabel}. Pour toute question ou contestation, contactez le support PROXIWORK.",
            'account_suspended',
            priority: 'important',
        ));

        return $updated;
    }

    public function activate(User $user, User $actor, string $reason, Request $request): User
    {
        $updated = DB::transaction(function () use ($user, $actor, $reason, $request): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->getKey() === $actor->getKey() || $target->hasRole('admin')) {
                throw ValidationException::withMessages([
                    'user' => ['Ce compte ne peut pas être réactivé depuis cette rubrique.'],
                ]);
            }

            if ($target->account_status !== UserAccountStatus::SUSPENDED) {
                throw ValidationException::withMessages([
                    'user' => ['Ce compte n’est pas suspendu.'],
                ]);
            }

            $previousReason = $target->suspension_reason;
            $previousUntil = $target->suspended_until?->toISOString();

            $target->account_status = UserAccountStatus::ACTIVE;
            $target->suspended_at = null;
            $target->suspended_until = null;
            $target->suspended_by = null;
            $target->suspension_reason = null;
            $target->suspension_note = null;
            $target->save();

            $this->auditLogService->record(
                'admin.user.activated',
                $target,
                $actor,
                [
                    'previous_status' => UserAccountStatus::SUSPENDED->value,
                    'new_status' => UserAccountStatus::ACTIVE->value,
                    'previous_reason_code' => $previousReason,
                    'previous_suspended_until' => $previousUntil,
                    'reason' => $reason,
                ],
                $request,
            );

            return $target->fresh(['roles']);
        });

        $updated->notify(new AccountActivityNotification(
            'Compte réactivé',
            'Votre compte PROXIWORK a été réactivé par l’administration.',
            'account_activated',
        ));

        return $updated;
    }

    public function expireSuspensions(): int
    {
        $expiredCount = 0;

        User::query()
            ->where('account_status', UserAccountStatus::SUSPENDED->value)
            ->whereNotNull('suspended_until')
            ->where('suspended_until', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$expiredCount): void {
                foreach ($users as $user) {
                    $expired = DB::transaction(function () use ($user): ?User {
                        $target = User::query()->lockForUpdate()->find($user->getKey());

                        if (
                            $target === null
                            || $target->account_status !== UserAccountStatus::SUSPENDED
                            || $target->suspended_until === null
                            || $target->suspended_until->isFuture()
                        ) {
                            return null;
                        }

                        $previousReason = $target->suspension_reason;
                        $previousUntil = $target->suspended_until->toISOString();

                        $target->account_status = UserAccountStatus::ACTIVE;
                        $target->suspended_at = null;
                        $target->suspended_until = null;
                        $target->suspended_by = null;
                        $target->suspension_reason = null;
                        $target->suspension_note = null;
                        $target->save();

                        $this->auditLogService->record(
                            'admin.user.suspension_expired',
                            $target,
                            null,
                            [
                                'previous_status' => UserAccountStatus::SUSPENDED->value,
                                'new_status' => UserAccountStatus::ACTIVE->value,
                                'previous_reason_code' => $previousReason,
                                'previous_suspended_until' => $previousUntil,
                                'actor' => 'system',
                            ],
                        );

                        return $target;
                    });

                    if ($expired !== null) {
                        $expired->notify(new AccountActivityNotification(
                            'Fin de suspension',
                            'La période de suspension de votre compte PROXIWORK est arrivée à son terme. Votre compte est de nouveau actif.',
                            'account_suspension_expired',
                        ));
                        $expiredCount++;
                    }
                }
            });

        return $expiredCount;
    }
}
