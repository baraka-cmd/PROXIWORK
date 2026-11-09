<?php

declare(strict_types=1);

namespace App\Services\Admin\User;

use App\Enums\UserAccountStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters)
    {
        $query = User::query()->with(['roles', 'profile', 'professionalProfile']);

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereKey(is_numeric($search) ? (int) $search : -1)
                    ->orWhereHas('profile', fn ($profile) => $profile->where('phone', 'like', "%{$search}%"))
                    ->orWhereHas('profile', fn ($profile) => $profile->whereRaw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, ''))) LIKE ?", ["%{$search}%"]));
            });
        }

        if (($role = $filters['role'] ?? null) !== null) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $role));
        }

        if (($status = $filters['account_status'] ?? null) !== null) {
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $query->where('account_status', $status);
        }

        if (($period = $filters['period'] ?? null) !== null) {
            $start = match ($period) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(6)->startOfDay(),
                '30d' => now()->subDays(29)->startOfDay(),
            };
            $query->where('created_at', '>=', $start);
        }

        if (! empty($filters['location'])) {
            $location = trim($filters['location']);
            $query->where(function ($q) use ($location): void {
                $q->whereHas('professionalProfile', fn ($profile) => $profile->where('city', 'like', "%{$location}%")
                    ->orWhere('province', 'like', "%{$location}%")
                    ->orWhere('commune', 'like', "%{$location}%"))
                    ->orWhereHas('addresses', fn ($address) => $address->where('city', 'like', "%{$location}%")
                        ->orWhere('province', 'like', "%{$location}%")
                        ->orWhere('commune', 'like', "%{$location}%"));
            });
        }

        if (array_key_exists('email_verified', $filters)) {
            $filters['email_verified']
                ? $query->whereNotNull('email_verified_at')
                : $query->whereNull('email_verified_at');
        }

        $query->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date));
        $query->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', $date));

        $sort = $filters['sort'] ?? '-created_at';
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        $query->orderBy($column, $direction);

        return $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
    }

    public function overview(): array
    {
        $base = User::query();
        $newSince = now()->subDays(29)->startOfDay();

        return [
            'total' => (clone $base)->count(),
            'clients' => (clone $base)->whereHas('roles', fn ($q) => $q->where('name', 'client'))->count(),
            'professionals' => (clone $base)->whereHas('roles', fn ($q) => $q->where('name', 'professional'))->count(),
            'active' => (clone $base)->where('account_status', UserAccountStatus::ACTIVE->value)->count(),
            'suspended' => (clone $base)->where('account_status', UserAccountStatus::SUSPENDED->value)->count(),
            'new' => (clone $base)->where('created_at', '>=', $newSince)->count(),
        ];
    }

    public function detail(User $user): array
    {
        $professionalId = $user->professionalProfile?->getKey();
        $requests = $user->serviceRequests()->count();
        $receivedRequests = $professionalId ? $user->professionalProfile->serviceRequests()->count() : 0;
        $clientOrders = Order::query()->where('client_id', $user->getKey())->count();
        $professionalOrders = $professionalId ? Order::query()->where('professional_id', $professionalId)->count() : 0;

        return [
            'client' => [
                'requests' => $requests,
                'quotations' => Quotation::query()->whereHas('serviceRequest', fn ($q) => $q->where('client_id', $user->getKey()))->count(),
                'orders' => $clientOrders,
                'completed_orders' => Order::query()->where('client_id', $user->getKey())->where('status', 'completed')->count(),
                'cancelled_orders' => Order::query()->where('client_id', $user->getKey())->where('status', 'cancelled')->count(),
                'disputes' => Order::query()->where('client_id', $user->getKey())->where('status', 'disputed')->count(),
            ],
            'professional' => $professionalId ? [
                'verification' => $user->professionalProfile->verification_status?->value,
                'profile_complete' => filled($user->professionalProfile->professional_title) && filled($user->professionalProfile->description)
                    && filled($user->professionalProfile->city) && filled($user->professionalProfile->province)
                    && $user->professionalProfile->skills()->exists(),
                'services' => $user->professionalProfile->services()->count(),
                'published_services' => $user->professionalProfile->services()->where('status', 'published')->count(),
                'requests' => $receivedRequests,
                'quotations' => Quotation::query()->whereHas('serviceRequest', fn ($q) => $q->where('professional_id', $professionalId))->count(),
                'orders' => $professionalOrders,
                'rating' => $user->professionalProfile->rating_average,
                'reviews' => $user->professionalProfile->rating_count,
                'availability' => $user->professionalProfile->availability_status?->value,
            ] : null,
            'activity' => AuditLog::query()->where('subject_type', User::class)
                ->where('subject_id', $user->getKey())->latest()->limit(20)->get(),
        ];
    }

    public function suspend(User $user, User $actor, Request $request): User
    {
        $changed = false;
        $updated = DB::transaction(function () use ($user, $actor, $request, &$changed): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->getKey() === $actor->getKey()) {
                throw ValidationException::withMessages([
                    'user' => ['Un administrateur ne peut pas suspendre son propre compte.'],
                ]);
            }

            if ($target->account_status === UserAccountStatus::SUSPENDED) {
                return $target->fresh(['roles']);
            }

            $target->account_status = UserAccountStatus::SUSPENDED->value;
            $target->save();
            $changed = true;
            $target->tokens()->delete();

            $this->auditLogService->record(
                'admin.user.suspended',
                $target,
                $actor,
                ['previous_status' => UserAccountStatus::ACTIVE->value, 'new_status' => UserAccountStatus::SUSPENDED->value],
                $request,
            );

            return $target->fresh(['roles']);
        });

        if ($changed) {
            $updated->notify(new AccountActivityNotification(
                'Compte suspendu',
                'Votre compte PROXIWORK a été suspendu par l’administration.',
                'account_suspended',
            ));
        }

        return $updated;
    }

    public function activate(User $user, User $actor, Request $request): User
    {
        return DB::transaction(function () use ($user, $actor, $request): User {
            $target = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($target->account_status === UserAccountStatus::ACTIVE) {
                return $target;
            }

            $target->account_status = UserAccountStatus::ACTIVE->value;
            $target->save();

            $this->auditLogService->record(
                'admin.user.activated',
                $target,
                $actor,
                ['previous_status' => UserAccountStatus::SUSPENDED->value, 'new_status' => UserAccountStatus::ACTIVE->value],
                $request,
            );

            return $target->fresh(['roles']);
        });
    }
}
