<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderDeliveryAttachment;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderWorkflowService
{
    public function proposeSchedule(
        Order $order,
        User $professional,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?string $note = null,
    ): Order {
        return DB::transaction(function () use ($order, $professional, $startsAt, $endsAt, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertProfessionalOwns($locked, $professional);

            if ($locked->status !== OrderStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'order' => 'Le planning ne peut être proposé que pour une commande confirmée et non démarrée.',
                ]);
            }

            if ($locked->schedule_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'schedule' => 'Le planning est déjà confirmé. Le client doit demander une modification avant une nouvelle proposition.',
                ]);
            }

            $latestScheduleEvent = $this->latestScheduleEvent($locked);
            if (data_get($latestScheduleEvent?->metadata, 'event') === 'schedule_proposed') {
                throw ValidationException::withMessages([
                    'schedule' => 'Une proposition attend la réponse du client. Demandez-lui une modification avant de proposer d’autres dates.',
                ]);
            }

            if ($startsAt->lessThanOrEqualTo(now()) || $endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'starts_at' => 'Choisissez une période future dont la fin est postérieure au début.',
                ]);
            }

            $this->record($locked, $locked->status, $locked->status, $professional, 'Le professionnel a proposé un planning.', [
                'event' => 'schedule_proposed',
                'proposed_start_at' => $startsAt->toISOString(),
                'proposed_end_at' => $endsAt->toISOString(),
                'note' => filled($note) ? trim((string) $note) : null,
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    public function confirmSchedule(Order $order, User $client): Order
    {
        return DB::transaction(function () use ($order, $client): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->client_id !== $client->getKey()) {
                abort(403, 'Vous ne pouvez pas confirmer le planning de cette commande.');
            }

            if ($locked->status !== OrderStatus::CONFIRMED || $locked->schedule_confirmed_at !== null) {
                throw ValidationException::withMessages([
                    'schedule' => 'Cette commande ne peut pas recevoir de confirmation de planning dans son état actuel.',
                ]);
            }

            $latestScheduleEvent = $this->latestScheduleEvent($locked);
            if ($latestScheduleEvent === null || data_get($latestScheduleEvent->metadata, 'event') !== 'schedule_proposed') {
                throw ValidationException::withMessages([
                    'schedule' => 'Aucune proposition de planning en attente de confirmation n’a été trouvée.',
                ]);
            }

            $startsAt = CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_start_at'));
            $endsAt = CarbonImmutable::parse(data_get($latestScheduleEvent->metadata, 'proposed_end_at'));
            if ($startsAt->lessThanOrEqualTo(now()) || $endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'schedule' => 'Cette proposition de planning n’est plus valide. Demandez une nouvelle proposition.',
                ]);
            }

            $now = now();
            $locked->forceFill([
                'scheduled_start_at' => $startsAt,
                'scheduled_end_at' => $endsAt,
                'schedule_confirmed_at' => $now,
            ])->save();

            $this->record($locked, $locked->status, $locked->status, $client, 'Le client a confirmé le planning proposé.', [
                'event' => 'schedule_confirmed',
                'proposal_history_id' => $latestScheduleEvent->getKey(),
                'scheduled_start_at' => $startsAt->toISOString(),
                'scheduled_end_at' => $endsAt->toISOString(),
                'confirmed_at' => $now->toISOString(),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    public function requestScheduleChange(Order $order, User $client, string $reason): Order
    {
        return DB::transaction(function () use ($order, $client, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->client_id !== $client->getKey()) {
                abort(403, 'Vous ne pouvez pas modifier le planning de cette commande.');
            }

            if ($locked->status !== OrderStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'schedule' => 'Le planning ne peut être modifié après le démarrage de la prestation par cette action.',
                ]);
            }

            $latestScheduleEvent = $this->latestScheduleEvent($locked);
            if ($latestScheduleEvent === null || ! in_array(data_get($latestScheduleEvent->metadata, 'event'), ['schedule_proposed', 'schedule_confirmed'], true)) {
                throw ValidationException::withMessages([
                    'schedule' => 'Aucun planning actif ne peut être modifié.',
                ]);
            }

            $locked->forceFill([
                'scheduled_start_at' => null,
                'scheduled_end_at' => null,
                'schedule_confirmed_at' => null,
            ])->save();

            $this->record($locked, $locked->status, $locked->status, $client, 'Le client a demandé une modification du planning.', [
                'event' => 'schedule_change_requested',
                'reason' => trim($reason),
                'previous_start_at' => $locked->scheduled_start_at?->toISOString()
                    ?? data_get($latestScheduleEvent->metadata, 'proposed_start_at')
                    ?? data_get($latestScheduleEvent->metadata, 'scheduled_start_at'),
                'previous_end_at' => $locked->scheduled_end_at?->toISOString()
                    ?? data_get($latestScheduleEvent->metadata, 'proposed_end_at')
                    ?? data_get($latestScheduleEvent->metadata, 'scheduled_end_at'),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    private function latestScheduleEvent(Order $order): ?OrderStatusHistory
    {
        return OrderStatusHistory::query()
            ->where('order_id', $order->getKey())
            ->latest('id')
            ->get()
            ->first(fn (OrderStatusHistory $history): bool => in_array(
                data_get($history->metadata, 'event'),
                ['schedule_proposed', 'schedule_confirmed', 'schedule_change_requested'],
                true,
            ));
    }

    public function startWork(Order $order, User $professional): Order
    {
        return DB::transaction(function () use ($order, $professional): Order {
            $locked = Order::query()->with('payment')->lockForUpdate()->findOrFail($order->getKey());
            $this->assertProfessionalOwns($locked, $professional);

            if ($locked->status !== OrderStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'order' => 'Le travail ne peut démarrer que pour une commande confirmée.',
                ]);
            }

            if ($locked->payment?->status !== PaymentStatus::SUCCEEDED) {
                throw ValidationException::withMessages([
                    'payment' => 'Le paiement doit être confirmé avant le démarrage de la prestation.',
                ]);
            }

            $latestScheduleEvent = $this->latestScheduleEvent($locked);
            if ($latestScheduleEvent !== null && in_array(
                data_get($latestScheduleEvent->metadata, 'event'),
                ['schedule_proposed', 'schedule_change_requested'],
                true,
            )) {
                throw ValidationException::withMessages([
                    'schedule' => 'Le planning doit être confirmé ou résolu avant le démarrage de la prestation.',
                ]);
            }

            if ($locked->schedule_confirmed_at !== null && $locked->scheduled_start_at?->isFuture()) {
                throw ValidationException::withMessages([
                    'schedule' => 'Le démarrage ne peut pas précéder la date convenue avec le client.',
                ]);
            }

            $previous = $locked->status;
            $now = now();
            $locked->forceFill([
                'status' => OrderStatus::IN_PROGRESS,
                'started_at' => $locked->started_at ?? $now,
            ])->save();

            $this->record($locked, $previous, OrderStatus::IN_PROGRESS, $professional, 'Le professionnel a démarré la prestation.', [
                'event' => 'work_started',
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    public function submitDelivery(Order $order, User $professional, string $summary, array $attachments = []): Order
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($order, $professional, $summary, $attachments, &$storedPaths): Order {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
                $this->assertProfessionalOwns($locked, $professional);

                if ($locked->status !== OrderStatus::IN_PROGRESS) {
                    throw ValidationException::withMessages([
                        'order' => 'Un livrable ne peut être soumis que lorsque la commande est en cours.',
                    ]);
                }

                $attachmentIds = [];
                foreach ($attachments as $file) {
                    $path = $file->store('order-deliveries/'.$locked->getKey(), 'local');

                    if ($path === false) {
                        throw ValidationException::withMessages([
                            'attachments' => 'Un fichier n’a pas pu être enregistré. Réessayez.',
                        ]);
                    }

                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
                    $attachment = OrderDeliveryAttachment::query()->forceCreate([
                        'order_id' => $locked->getKey(),
                        'submitted_by' => $professional->getKey(),
                        'path' => $path,
                        'original_name' => mb_substr($originalName, 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => (int) $file->getSize(),
                        'created_at' => now(),
                    ]);
                    $attachmentIds[] = $attachment->getKey();
                }

                $previous = $locked->status;
                $locked->forceFill(['status' => OrderStatus::DELIVERY_SUBMITTED])->save();

                $this->record($locked, $previous, OrderStatus::DELIVERY_SUBMITTED, $professional, 'Le professionnel a soumis le résultat pour réception par le client.', [
                    'event' => 'delivery_submitted',
                    'delivery_summary' => trim($summary),
                    'attachment_ids' => $attachmentIds,
                    'submitted_at' => now()->toISOString(),
                ]);

                return $locked->refresh()->load(['client', 'professional.user', 'statusHistories', 'deliveryAttachments']);
            }, attempts: 1);
        } catch (Throwable $exception) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $exception;
        }
    }

    public function acceptDelivery(Order $order, User $client): Order
    {
        return DB::transaction(function () use ($order, $client): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->client_id !== $client->getKey()) {
                abort(403, 'Vous ne pouvez pas confirmer la réception de cette commande.');
            }

            if ($locked->status !== OrderStatus::DELIVERY_SUBMITTED) {
                throw ValidationException::withMessages([
                    'order' => 'Seul un livrable soumis peut être confirmé.',
                ]);
            }

            $previous = $locked->status;
            $now = now();
            $locked->forceFill([
                'status' => OrderStatus::COMPLETED,
                'completed_at' => $now,
            ])->save();

            $this->record($locked, $previous, OrderStatus::COMPLETED, $client, 'Le client a confirmé la réception du livrable.', [
                'event' => 'delivery_accepted',
                'accepted_at' => $now->toISOString(),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    public function requestDeliveryChanges(Order $order, User $client, string $reason): Order
    {
        return DB::transaction(function () use ($order, $client, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($locked->client_id !== $client->getKey()) {
                abort(403, 'Vous ne pouvez pas demander une correction pour cette commande.');
            }

            if ($locked->status !== OrderStatus::DELIVERY_SUBMITTED) {
                throw ValidationException::withMessages([
                    'order' => 'Une correction ne peut être demandée qu’après la soumission d’un livrable.',
                ]);
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => OrderStatus::IN_PROGRESS])->save();

            $this->record($locked, $previous, OrderStatus::IN_PROGRESS, $client, 'Le client a demandé une correction du livrable.', [
                'event' => 'delivery_changes_requested',
                'reason' => trim($reason),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    public function reportBlocker(Order $order, User $professional, string $description): Order
    {
        return DB::transaction(function () use ($order, $professional, $description): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertProfessionalOwns($locked, $professional);

            if ($locked->status !== OrderStatus::IN_PROGRESS) {
                throw ValidationException::withMessages([
                    'order' => 'Un blocage ne peut être signalé que pendant la réalisation.',
                ]);
            }

            $this->record($locked, OrderStatus::IN_PROGRESS, OrderStatus::IN_PROGRESS, $professional, 'Le professionnel a signalé un blocage.', [
                'event' => 'work_blocked',
                'description' => trim($description),
            ]);

            return $locked->refresh()->load(['client', 'professional.user', 'statusHistories']);
        }, attempts: 3);
    }

    private function assertProfessionalOwns(Order $order, User $professional): void
    {
        if ($order->professional?->user_id !== $professional->getKey()) {
            abort(403, 'Vous ne pouvez pas modifier cette commande.');
        }
    }

    private function record(
        Order $order,
        ?OrderStatus $from,
        OrderStatus $to,
        User $actor,
        string $reason,
        array $metadata,
    ): void {
        OrderStatusHistory::query()->forceCreate([
            'order_id' => $order->getKey(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $actor->getKey(),
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
