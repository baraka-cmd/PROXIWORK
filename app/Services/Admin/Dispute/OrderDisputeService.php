<?php

declare(strict_types=1);

namespace App\Services\Admin\Dispute;

use App\Enums\OrderDisputePriority;
use App\Enums\OrderDisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderDispute;
use App\Models\OrderDisputeMessage;
use App\Models\OrderDisputeAttachment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class OrderDisputeService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly TransactionalNotificationService $notifications,
    ) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = OrderDispute::query()->with([
            'order:id,order_number,status',
            'client:id,name,email',
            'professional:id,user_id,business_name',
            'professional.user:id,name,email',
            'assignee:id,name',
            'opener:id,name',
        ]);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('reason_code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"));
            });
        }

        foreach (['status', 'priority', 'assigned_to', 'reason_code'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where($field, $filters[$field]);
            }
        }

        return $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    public function overview(): array
    {
        return [
            'open' => OrderDispute::query()->whereIn('status', [
                OrderDisputeStatus::OPEN->value, OrderDisputeStatus::REOPENED->value,
            ])->count(),
            'under_review' => OrderDispute::query()->where('status', OrderDisputeStatus::UNDER_REVIEW->value)->count(),
            'awaiting_parties' => OrderDispute::query()->whereIn('status', [
                OrderDisputeStatus::AWAITING_CLIENT->value, OrderDisputeStatus::AWAITING_PROFESSIONAL->value,
            ])->count(),
            'mediation' => OrderDispute::query()->where('status', OrderDisputeStatus::MEDIATION->value)->count(),
            'decision_pending' => OrderDispute::query()->where('status', OrderDisputeStatus::DECISION_PENDING->value)->count(),
            'resolved' => OrderDispute::query()->where('status', OrderDisputeStatus::RESOLVED->value)->count(),
        ];
    }

    public function show(OrderDispute $dispute): OrderDispute
    {
        return $dispute->load([
            'order.client:id,name,email',
            'order.professional.user:id,name,email',
            'order.quotation',
            'order.acceptedOffer',
            'order.statusHistories.changedBy:id,name',
            'order.payment',
            'client:id,name,email',
            'professional.user:id,name,email',
            'opener:id,name,email',
            'assignee:id,name,email',
            'resolver:id,name,email',
            'events.actor:id,name',
            'messages' => fn ($q) => $q->when(
                ! auth()->user()?->hasPermissionTo('disputes.manage'),
                fn ($messages) => $messages->where('visibility', 'participants'),
            )->oldest()->with('sender:id,name'),
            'attachments.uploader:id,name',
        ]);
    }

    public function openForOrder(Order $order, User $actor, string $reasonCode, string $description, Request $request): OrderDispute
    {
        return DB::transaction(function () use ($order, $actor, $reasonCode, $description, $request): OrderDispute {
            $lockedOrder = Order::query()->with('professional')->lockForUpdate()->findOrFail($order->getKey());
            $professionalUserId = $lockedOrder->professional?->user_id;
            $isClient = (int) $lockedOrder->client_id === (int) $actor->getKey();
            $isProfessional = (int) $professionalUserId === (int) $actor->getKey();

            if (! $isClient && ! $isProfessional) {
                abort(403);
            }

            if (in_array($lockedOrder->status, [OrderStatus::PENDING_PAYMENT, OrderStatus::CANCELLED], true)) {
                throw ValidationException::withMessages(['order' => 'Cette commande ne peut pas faire l’objet d’un litige dans son état actuel.']);
            }

            $existingDispute = OrderDispute::query()->where('order_id', $lockedOrder->getKey())->exists();

            if ($existingDispute) {
                throw ValidationException::withMessages(['order' => 'Un dossier de litige existe déjà pour cette commande. Consultez ce dossier au lieu d’en créer un second.']);
            }

            $dispute = OrderDispute::create([
                'order_id' => $lockedOrder->getKey(),
                'opened_by' => $actor->getKey(),
                'client_id' => $lockedOrder->client_id,
                'professional_id' => $lockedOrder->professional_id,
                'reason_code' => $reasonCode,
                'description' => $description,
                'status' => OrderDisputeStatus::OPEN,
                'priority' => OrderDisputePriority::NORMAL,
            ]);

            $dispute->events()->create([
                'actor_id' => $actor->getKey(),
                'event_type' => 'dispute_opened',
                'to_status' => OrderDisputeStatus::OPEN->value,
                'reason' => $description,
                'created_at' => now(),
            ]);

            $this->audit->record('order_dispute.opened', $dispute, $actor, ['order_id' => $lockedOrder->getKey(), 'reason_code' => $reasonCode], $request);
            DB::afterCommit(function () use ($dispute, $lockedOrder, $actor): void {
                $client = $lockedOrder->client()->first();
                $professionalUser = $lockedOrder->professional()->with('user')->first()?->user;
                $recipient = (int) $actor->getKey() === (int) $lockedOrder->client_id ? $professionalUser : $client;
                if ($recipient !== null) {
                    $url = (int) $recipient->getKey() === (int) $lockedOrder->client_id
                        ? route('client.disputes.show', $dispute)
                        : route('professional.disputes.show', $dispute);
                    $this->notifications->accountActivity(
                        $recipient,
                        'Nouveau litige sur une commande',
                        'Un dossier de litige a été ouvert pour une commande à laquelle vous participez.',
                        $url,
                        'order-dispute-opened:'.$dispute->getKey().':'.$recipient->getKey(),
                        'important',
                    );
                }
            });

            return $dispute->load(['order', 'client', 'professional']);
        }, attempts: 3);
    }

    public function assign(OrderDispute $dispute, User $assignee, User $actor, Request $request): OrderDispute
    {
        if (! $assignee->isActive() || ! $assignee->hasPermissionTo('disputes.manage')) {
            throw ValidationException::withMessages(['assigned_to' => 'Le responsable doit être un modérateur actif autorisé à gérer les litiges.']);
        }

        return DB::transaction(function () use ($dispute, $assignee, $actor, $request): OrderDispute {
            $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
            if (! $locked->status->isActive()) {
                throw ValidationException::withMessages(['status' => 'Un litige clôturé ne peut plus être assigné.']);
            }
            $previous = $locked->assigned_to;
            $locked->update(['assigned_to' => $assignee->getKey()]);
            $locked->events()->create([
                'actor_id' => $actor->getKey(), 'event_type' => 'assigned',
                'reason' => 'Affectation du dossier', 'metadata' => ['from' => $previous, 'to' => $assignee->getKey()],
                'created_at' => now(),
            ]);
            $this->audit->record('order_dispute.assigned', $locked, $actor, ['assigned_to' => $assignee->getKey()], $request);
            return $locked->fresh(['assignee']);
        });
    }

    public function transition(OrderDispute $dispute, OrderDisputeStatus $to, string $reason, User $actor, Request $request): OrderDispute
    {
        return DB::transaction(function () use ($dispute, $to, $reason, $actor, $request): OrderDispute {
            $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
            $from = $locked->status;
            $allowed = match ($from) {
                OrderDisputeStatus::OPEN, OrderDisputeStatus::REOPENED => [OrderDisputeStatus::UNDER_REVIEW, OrderDisputeStatus::CLOSED],
                OrderDisputeStatus::UNDER_REVIEW => [OrderDisputeStatus::AWAITING_CLIENT, OrderDisputeStatus::AWAITING_PROFESSIONAL, OrderDisputeStatus::MEDIATION, OrderDisputeStatus::DECISION_PENDING, OrderDisputeStatus::CLOSED],
                OrderDisputeStatus::AWAITING_CLIENT, OrderDisputeStatus::AWAITING_PROFESSIONAL => [OrderDisputeStatus::UNDER_REVIEW, OrderDisputeStatus::MEDIATION, OrderDisputeStatus::CLOSED],
                OrderDisputeStatus::MEDIATION => [OrderDisputeStatus::UNDER_REVIEW, OrderDisputeStatus::DECISION_PENDING, OrderDisputeStatus::CLOSED],
                OrderDisputeStatus::DECISION_PENDING => [OrderDisputeStatus::RESOLVED, OrderDisputeStatus::UNDER_REVIEW],
                OrderDisputeStatus::RESOLVED => [OrderDisputeStatus::CLOSED],
                OrderDisputeStatus::CLOSED => [],
            };

            if (! in_array($to, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'Cette transition de litige n’est pas autorisée.']);
            }

            $locked->update([
                'status' => $to,
                'resolved_at' => $to === OrderDisputeStatus::RESOLVED ? now() : $locked->resolved_at,
                'closed_at' => $to === OrderDisputeStatus::CLOSED ? now() : $locked->closed_at,
            ]);
            $locked->events()->create([
                'actor_id' => $actor->getKey(), 'event_type' => 'status_changed',
                'from_status' => $from->value, 'to_status' => $to->value,
                'reason' => $reason, 'created_at' => now(),
            ]);
            $this->audit->record('order_dispute.status_changed', $locked, $actor, ['from' => $from->value, 'to' => $to->value, 'reason' => $reason], $request);
            DB::afterCommit(function () use ($locked, $to): void {
                $client = $locked->client()->first();
                $professionalUser = $locked->professional()->with('user')->first()?->user;
                $recipients = match ($to) {
                    OrderDisputeStatus::AWAITING_CLIENT => array_filter([$client]),
                    OrderDisputeStatus::AWAITING_PROFESSIONAL => array_filter([$professionalUser]),
                    default => array_filter([$client, $professionalUser]),
                };
                foreach ($recipients as $recipient) {
                    $url = (int) $recipient->getKey() === (int) $locked->client_id
                        ? route('client.disputes.show', $locked)
                        : route('professional.disputes.show', $locked);
                    $this->notifications->accountActivity(
                        $recipient,
                        'Mise à jour de votre litige',
                        'Le dossier de litige lié à votre commande a changé d’étape.',
                        $url,
                        'order-dispute-status:'.$locked->getKey().':'.$to->value.':'.$recipient->getKey(),
                        'important',
                    );
                }
            });
            return $locked->fresh(['events.actor', 'assignee']);
        });
    }

    public function reopen(OrderDispute $dispute, string $reason, User $actor, Request $request): OrderDispute
    {
        return DB::transaction(function () use ($dispute, $reason, $actor, $request): OrderDispute {
            $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
            if ($locked->status !== OrderDisputeStatus::CLOSED) {
                throw ValidationException::withMessages(['status' => 'Seul un dossier clôturé peut être rouvert.']);
            }
            $locked->update([
                'status' => OrderDisputeStatus::REOPENED,
                'resolved_by' => null,
                'resolved_at' => null,
                'closed_at' => null,
                'resolution_code' => null,
                'resolution_note' => null,
            ]);
            $locked->events()->create([
                'actor_id' => $actor->getKey(),
                'event_type' => 'reopened',
                'from_status' => OrderDisputeStatus::CLOSED->value,
                'to_status' => OrderDisputeStatus::REOPENED->value,
                'reason' => $reason,
                'created_at' => now(),
            ]);
            $this->audit->record('order_dispute.reopened', $locked, $actor, ['reason' => $reason], $request);
            return $locked->fresh(['events.actor', 'assignee']);
        });
    }

    public function decide(OrderDispute $dispute, string $resolutionCode, string $resolutionNote, User $actor, Request $request): OrderDispute
    {
        return DB::transaction(function () use ($dispute, $resolutionCode, $resolutionNote, $actor, $request): OrderDispute {
            $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
            if ($locked->status !== OrderDisputeStatus::DECISION_PENDING) {
                throw ValidationException::withMessages(['status' => 'Le dossier doit être en attente de décision avant de pouvoir être résolu.']);
            }
            $locked->update([
                'status' => OrderDisputeStatus::RESOLVED,
                'resolution_code' => $resolutionCode,
                'resolution_note' => $resolutionNote,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now(),
            ]);
            $locked->events()->create([
                'actor_id' => $actor->getKey(), 'event_type' => 'decision_recorded',
                'from_status' => OrderDisputeStatus::DECISION_PENDING->value,
                'to_status' => OrderDisputeStatus::RESOLVED->value,
                'reason' => $resolutionNote, 'metadata' => ['resolution_code' => $resolutionCode],
                'created_at' => now(),
            ]);
            $this->audit->record('order_dispute.decision_recorded', $locked, $actor, [
                'resolution_code' => $resolutionCode,
                'financial_action_required' => $resolutionCode === 'financial_review_required',
            ], $request);
            DB::afterCommit(function () use ($locked): void {
                $client = $locked->client()->first();
                $professionalUser = $locked->professional()->with('user')->first()?->user;
                foreach (array_filter([$client, $professionalUser]) as $recipient) {
                    $url = (int) $recipient->getKey() === (int) $locked->client_id
                        ? route('client.disputes.show', $locked)
                        : route('professional.disputes.show', $locked);
                    $this->notifications->accountActivity(
                        $recipient,
                        'Décision rendue sur votre litige',
                        'L’administration a enregistré une décision motivée. Consultez le dossier pour en prendre connaissance.',
                        $url,
                        'order-dispute-decision:'.$locked->getKey().':'.$recipient->getKey(),
                        'important',
                    );
                }
            });
            return $locked->fresh(['resolver', 'events.actor']);
        });
    }


    public function addAttachment(OrderDispute $dispute, User $actor, UploadedFile $file, Request $request): OrderDisputeAttachment
    {
        $path = null;

        try {
            return DB::transaction(function () use ($dispute, $actor, $file, $request, &$path): OrderDisputeAttachment {
                $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
                $isAdmin = $actor->hasPermissionTo('disputes.manage');
                $isParty = (int) $locked->client_id === (int) $actor->getKey()
                    || (int) $locked->professional()->value('user_id') === (int) $actor->getKey();

                if (! $isAdmin && ! $isParty) {
                    abort(403);
                }
                if (! $locked->status->isActive()) {
                    throw ValidationException::withMessages(['file' => 'Un dossier clôturé ne peut plus recevoir de preuve.']);
                }
                if ($locked->attachments()->count() >= 20) {
                    throw ValidationException::withMessages(['file' => 'Ce dossier a atteint la limite de 20 pièces justificatives.']);
                }
                if ((int) $locked->attachments()->sum('size') + $file->getSize() > 50 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => 'La taille totale des pièces justificatives ne peut pas dépasser 50 Mo par dossier.']);
                }

                $path = $file->store('order-disputes/'.$locked->getKey(), 'local');
                $attachment = $locked->attachments()->create([
                    'uploaded_by' => $actor->getKey(),
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                $locked->events()->create([
                    'actor_id' => $actor->getKey(),
                    'event_type' => 'evidence_uploaded',
                    'reason' => 'Une pièce justificative a été ajoutée.',
                    'metadata' => ['attachment_id' => $attachment->getKey()],
                    'created_at' => now(),
                ]);
                $this->audit->record('order_dispute.evidence_uploaded', $locked, $actor, [
                    'attachment_id' => $attachment->getKey(),
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ], $request);

                return $attachment->load('uploader');
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }

    public function message(OrderDispute $dispute, User $sender, string $body, bool $internal, Request $request): OrderDisputeMessage
    {
        return DB::transaction(function () use ($dispute, $sender, $body, $internal, $request): OrderDisputeMessage {
            $locked = OrderDispute::query()->lockForUpdate()->findOrFail($dispute->getKey());
            $isAdmin = $sender->hasPermissionTo('disputes.manage');
            $isParty = (int) $locked->client_id === (int) $sender->getKey()
                || (int) $locked->professional()->value('user_id') === (int) $sender->getKey();

            if (! $isAdmin && ! $isParty) {
                abort(403);
            }
            if ($internal && ! $isAdmin) {
                abort(403);
            }
            if (! $locked->status->isActive()) {
                throw ValidationException::withMessages(['message' => 'Ce dossier est clôturé et ne reçoit plus de message.']);
            }

            $message = $locked->messages()->create([
                'sender_id' => $sender->getKey(),
                'body' => $body,
                'visibility' => $internal ? 'internal' : 'participants',
            ]);
            $locked->events()->create([
                'actor_id' => $sender->getKey(), 'event_type' => $internal ? 'internal_note_added' : 'message_added',
                'reason' => $internal ? 'Note interne ajoutée' : 'Message ajouté au dossier',
                'metadata' => ['message_id' => $message->getKey(), 'visibility' => $internal ? 'internal' : 'participants'],
                'created_at' => now(),
            ]);
            $this->audit->record('order_dispute.message_added', $locked, $sender, ['message_id' => $message->getKey(), 'internal' => $internal], $request);
            if (! $internal) {
                DB::afterCommit(function () use ($locked, $sender, $message): void {
                    $client = $locked->client()->first();
                    $professionalUser = $locked->professional()->with('user')->first()?->user;
                    $recipients = (int) $sender->getKey() === (int) $locked->client_id
                        ? array_filter([$professionalUser])
                        : ((int) $sender->getKey() === (int) $professionalUser?->getKey()
                            ? array_filter([$client])
                            : array_filter([$client, $professionalUser]));
                    foreach ($recipients as $recipient) {
                        $url = (int) $recipient->getKey() === (int) $locked->client_id
                            ? route('client.disputes.show', $locked)
                            : route('professional.disputes.show', $locked);
                        $this->notifications->accountActivity(
                            $recipient,
                            'Nouveau message sur un litige',
                            'Un nouveau message est disponible dans le dossier de litige.',
                            $url,
                            'order-dispute-message:'.$message->getKey().':'.$recipient->getKey(),
                            'important',
                        );
                    }
                });
            }
            return $message->load('sender');
        });
    }
}
