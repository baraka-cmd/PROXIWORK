<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PaymentRefundStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRefundStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $refundId,
        public readonly int $paymentId,
        public readonly PaymentRefundStatus $status,
        public readonly PaymentRefundStatus $previousStatus,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = match ($this->status) {
            PaymentRefundStatus::PROCESSING => 'Votre demande de remboursement est en cours d’examen. Aucun remboursement n’est encore confirmé.',
            PaymentRefundStatus::REJECTED => 'Votre demande de remboursement a été refusée après examen.',
            PaymentRefundStatus::SUCCEEDED => 'Le résultat de votre remboursement a été confirmé.',
            PaymentRefundStatus::FAILED => 'Le traitement de votre remboursement a échoué. La demande reste consultable dans votre espace paiements.',
            PaymentRefundStatus::REQUESTED => 'Votre demande de remboursement a été enregistrée.',
            PaymentRefundStatus::CANCELLED => 'Votre demande de remboursement a été annulée.',
        };

        return [
            'kind' => 'payment_refund_status_changed',
            'action' => 'payment',
            'priority' => 'important',
            'title' => 'Mise à jour de votre remboursement',
            'message' => $message,
            'refund_id' => $this->refundId,
            'payment_id' => $this->paymentId,
            'status' => $this->status->value,
            'previous_status' => $this->previousStatus->value,
            'url' => route('client.payments.show', $this->paymentId),
        ];
    }
}
