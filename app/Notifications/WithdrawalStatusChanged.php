<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\WithdrawalStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WithdrawalStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $withdrawalId,
        public readonly WithdrawalStatus $status,
        public readonly ?WithdrawalStatus $previousStatus,
        public readonly ?string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        // Database notification only. No email/SMS/push channel is configured
        // or implied by the current manual payout workflow.
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = match ($this->status) {
            WithdrawalStatus::REQUESTED => 'Votre demande est enregistrée et les fonds sont réservés. Aucun paiement n’est encore confirmé.',
            WithdrawalStatus::PROCESSING => 'Le traitement manuel de votre retrait a commencé. Le paiement n’est pas encore confirmé.',
            WithdrawalStatus::REVIEW_REQUIRED => 'Le résultat du transfert doit être vérifié. Les fonds restent réservés jusqu’au rapprochement.',
            WithdrawalStatus::SUCCEEDED => 'Le retrait a été confirmé après vérification de la preuve de paiement.',
            WithdrawalStatus::FAILED => 'L’échec du retrait a été confirmé et les fonds réservés ont été libérés.',
            WithdrawalStatus::REJECTED => 'Votre demande de retrait a été refusée et les fonds réservés ont été libérés.',
            WithdrawalStatus::CANCELLED => 'Votre demande a été annulée avant le début du traitement et les fonds ont été libérés.',
        };

        return [
            'kind' => 'withdrawal_status_changed',
            'title' => 'Mise à jour de votre retrait',
            'message' => $message,
            'withdrawal_id' => $this->withdrawalId,
            'reference' => 'WD-'.str_pad((string) $this->withdrawalId, 8, '0', STR_PAD_LEFT),
            'previous_status' => $this->previousStatus?->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'reason' => $this->reason,
            'url' => route('professional.withdrawals.show', $this->withdrawalId),
        ];
    }
}
