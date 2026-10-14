<?php

declare(strict_types=1);

namespace App\Enums;

enum WithdrawalStatus: string
{
    case REQUESTED = 'requested';
    case PROCESSING = 'processing';
    case REVIEW_REQUIRED = 'review_required';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function isFinal(): bool
    {
        return match ($this) {
            self::SUCCEEDED, self::FAILED, self::CANCELLED => true,
            self::REQUESTED, self::PROCESSING, self::REVIEW_REQUIRED => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED => 'En attente',
            self::PROCESSING => 'En traitement',
            self::REVIEW_REQUIRED => 'À vérifier',
            self::SUCCEEDED => 'Réussi',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
        };
    }
}
