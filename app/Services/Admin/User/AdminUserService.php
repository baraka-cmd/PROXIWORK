<?php

declare(strict_types=1);

namespace App\Services\Admin\User;

use App\Enums\UserAccountStatus;
use BackedEnum;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
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
        $query = User::query()->with('roles');

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (($role = $filters['role'] ?? null) !== null) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $role));
        }

        if (($status = $filters['account_status'] ?? null) !== null) {
            $status = $status instanceof BackedEnum ? $status->value : $status;
            $query->where('account_status', $status);
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
