<?php

declare(strict_types=1);

namespace App\Services\Admin\Support;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminSupportManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'assignee:id,name'])
            ->withCount('messages');

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($query) use ($search): void {
                $query->where('subject', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($user) use ($search): void {
                        $user->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['category', 'priority', 'status', 'assigned_to'] as $filter) {
            if (($value = $filters[$filter] ?? null) !== null && $value !== '') {
                $query->where($filter, $value);
            }
        }

        $query->when($filters['created_from'] ?? null, fn ($q, $date) => $q->where('created_at', '>=', $date));
        $query->when($filters['created_to'] ?? null, fn ($q, $date) => $q->where('created_at', '<=', $date));

        $sort = $filters['sort'] ?? '-last_message_at';
        $column = ltrim($sort, '-');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        return $query
            ->orderBy($column, $direction)
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    public function assignableUsers()
    {
        return User::query()
            ->where('account_status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('name', 'support.manage'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function show(SupportTicket $ticket): SupportTicket
    {
        return $ticket->load([
            'user:id,name,email',
            'assignee:id,name,email',
            'messages' => fn ($query) => $query->oldest()->with('sender:id,name,email'),
        ]);
    }

    public function assign(
        SupportTicket $ticket,
        User $assignee,
        User $actor,
        Request $request,
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $assignee, $actor, $request): SupportTicket {
            $target = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if (! $assignee->isActive() || ! $assignee->hasPermissionTo('support.manage')) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['Cet utilisateur ne peut pas recevoir de ticket Support.'],
                ]);
            }

            if ($target->status === SupportTicketStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'status' => ['Un ticket clôturé ne peut plus être réassigné.'],
                ]);
            }

            $target->forceFill(['assigned_to' => $assignee->getKey()])->save();

            $this->auditLogService->record(
                'admin.support.assigned',
                $target,
                $actor,
                ['assigned_to' => $assignee->getKey()],
                $request,
            );

            return $target->fresh(['user', 'assignee']);
        });
    }

    public function updateTicket(
        SupportTicket $ticket,
        array $data,
        User $actor,
        Request $request,
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $data, $actor, $request): SupportTicket {
            $target = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if ($target->status === SupportTicketStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'status' => ['Un ticket clôturé ne peut plus être modifié.'],
                ]);
            }

            $changes = [];

            if (isset($data['priority'])) {
                $changes['priority'] = $data['priority'];
            }

            if (isset($data['status'])) {
                $newStatus = SupportTicketStatus::from($data['status']);
                $this->assertTransition($target->status, $newStatus);
                $changes['status'] = $newStatus;
                if ($newStatus === SupportTicketStatus::IN_PROGRESS) {
                    $changes['resolved_at'] = null;
                }
            }

            if ($changes !== []) {
                $target->forceFill($changes)->save();

                $this->auditLogService->record(
                    'admin.support.updated',
                    $target,
                    $actor,
                    ['changes' => array_map(
                        static fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
                        $changes,
                    )],
                    $request,
                );
            }

            return $target->fresh(['user', 'assignee']);
        });
    }

    public function reply(
        SupportTicket $ticket,
        string $body,
        User $actor,
        Request $request,
    ): TicketMessage {
        return DB::transaction(function () use ($ticket, $body, $actor, $request): TicketMessage {
            $target = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if ($target->status === SupportTicketStatus::CLOSED) {
                throw ValidationException::withMessages([
                    'body' => ['Un ticket clôturé ne peut plus recevoir de réponse.'],
                ]);
            }

            $message = TicketMessage::query()->create([
                'ticket_id' => $target->getKey(),
                'sender_id' => $actor->getKey(),
                'body' => $body,
            ]);

            $target->forceFill([
                'status' => SupportTicketStatus::IN_PROGRESS,
                'last_message_at' => now(),
                'resolved_at' => null,
            ])->save();

            $this->auditLogService->record(
                'admin.support.replied',
                $target,
                $actor,
                ['message_id' => $message->getKey()],
                $request,
            );

            return $message->load('sender:id,name,email');
        });
    }

    public function resolve(
        SupportTicket $ticket,
        User $actor,
        Request $request,
    ): SupportTicket {
        return $this->transition(
            $ticket,
            SupportTicketStatus::RESOLVED,
            $actor,
            $request,
            'admin.support.resolved',
        );
    }

    public function close(
        SupportTicket $ticket,
        User $actor,
        Request $request,
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $actor, $request): SupportTicket {
            $target = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if ($target->status !== SupportTicketStatus::RESOLVED) {
                throw ValidationException::withMessages([
                    'status' => ['Seul un ticket résolu peut être clôturé.'],
                ]);
            }

            $target->forceFill([
                'status' => SupportTicketStatus::CLOSED,
                'closed_at' => now(),
            ])->save();

            $this->auditLogService->record(
                'admin.support.closed',
                $target,
                $actor,
                [],
                $request,
            );

            return $target->fresh(['user', 'assignee']);
        });
    }

    private function transition(
        SupportTicket $ticket,
        SupportTicketStatus $to,
        User $actor,
        Request $request,
        string $auditAction,
    ): SupportTicket {
        return DB::transaction(function () use ($ticket, $to, $actor, $request, $auditAction): SupportTicket {
            $target = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->getKey());

            if (in_array($target->status, [SupportTicketStatus::RESOLVED, SupportTicketStatus::CLOSED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Ce ticket est déjà résolu ou clôturé.'],
                ]);
            }

            $target->forceFill([
                'status' => $to,
                'resolved_at' => now(),
            ])->save();

            $this->auditLogService->record($auditAction, $target, $actor, [], $request);

            return $target->fresh(['user', 'assignee']);
        });
    }

    private function assertTransition(
        SupportTicketStatus $from,
        SupportTicketStatus $to,
    ): void {
        $allowed = match ($from) {
            SupportTicketStatus::OPEN => [SupportTicketStatus::IN_PROGRESS, SupportTicketStatus::WAITING],
            SupportTicketStatus::IN_PROGRESS => [SupportTicketStatus::WAITING],
            SupportTicketStatus::WAITING => [SupportTicketStatus::IN_PROGRESS],
            SupportTicketStatus::RESOLVED => [],
            SupportTicketStatus::CLOSED => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['Transition de statut non autorisée.'],
            ]);
        }
    }
}
