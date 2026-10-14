<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class MoneyFormatter
{
    /**
     * Format an unsigned decimal amount without converting it to a float.
     *
     * Financial amounts may exceed the range where a binary float can preserve
     * every cent. Formatting is a presentation concern and must not introduce
     * rounding errors into the displayed decimal string.
     */
    public static function format(string|int $amount): string
    {
        $value = trim((string) $amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Montant financier invalide pour affichage.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $whole) ?? $whole;

        return $whole.','.str_pad($fraction, 2, '0');
    }
}
