<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Currency;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CurrencyService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function all()
    {
        return Currency::query()->orderByDesc('is_default')->orderBy('code')->get();
    }

    public function lockActive(string $code): Currency
    {
        $currency = Currency::query()->whereKey(strtoupper($code))->lockForUpdate()->first();

        if ($currency === null || ! $currency->is_active) {
            throw ValidationException::withMessages(['currency' => 'Cette devise est inactive ou indisponible pour une nouvelle commande.']);
        }

        return $currency;
    }

    public function isActive(?string $code): bool
    {
        return $code !== null && Currency::query()->whereKey(strtoupper($code))->where('is_active', true)->exists();
    }

    public function defaultCode(): string
    {
        return (string) Currency::query()->where('is_default', true)->value('code');
    }

    public function update(string $code, bool $active, bool $makeDefault, User $actor, Request $request): Currency
    {
        return $this->database->transaction(function () use ($code, $active, $makeDefault, $actor, $request): Currency {
            $currency = Currency::query()->whereKey(strtoupper($code))->lockForUpdate()->firstOrFail();

            if ($makeDefault && ! $active) {
                throw ValidationException::withMessages(['currency' => 'La devise principale doit rester active.']);
            }

            if (! $active && $currency->is_default) {
                throw ValidationException::withMessages(['currency' => 'Définissez d’abord une autre devise principale avant de désactiver celle-ci.']);
            }

            if (! $active && $currency->is_active) {
                $this->assertNoOpenOperations($currency);
            }

            $before = ['is_active' => $currency->is_active, 'is_default' => $currency->is_default];

            if ($makeDefault) {
                Currency::query()->where('is_default', true)->lockForUpdate()->first();
                Currency::query()->where('is_default', true)->update(['is_default' => false]);
            }

            $currency->forceFill([
                'is_active' => $active,
                'is_default' => $makeDefault || $currency->is_default,
            ])->save();

            if ($before['is_active'] !== $currency->is_active || $before['is_default'] !== $currency->is_default) {
                $this->auditLogs->recordEvent(
                    action: 'admin.settings.currency.updated',
                    subject: $currency,
                    user: $actor,
                    metadata: ['currency' => $currency->code, 'previous' => $before, 'current' => ['is_active' => $currency->is_active, 'is_default' => $currency->is_default]],
                    request: $request,
                    category: 'administrative',
                    reason: 'Modification de la configuration d’une devise.',
                );
            }

            return $currency->refresh();
        }, attempts: 3);
    }

    private function assertNoOpenOperations(Currency $currency): void
    {
        $code = $currency->code;

        $hasPublishedServices = Service::query()->where('currency', $code)->where('status', 'published')->exists();
        $hasOpenRequests = ServiceRequest::query()->where('currency', $code)->whereIn('status', ['draft', 'requested', 'quoted', 'accepted'])->exists();
        $hasPendingOrders = Order::query()->where('currency', $code)->where('status', 'pending_payment')->exists();
        $hasWalletFunds = Wallet::query()
            ->where('currency', $code)
            ->where(function ($query): void {
                $query->where('available_balance', '>', 0)
                    ->orWhere('pending_balance', '>', 0)
                    ->orWhere('locked_balance', '>', 0);
            })
            ->exists();

        if ($hasPublishedServices || $hasOpenRequests || $hasPendingOrders || $hasWalletFunds) {
            throw ValidationException::withMessages([
                'currency' => 'Cette devise est utilisée par des services publiés, des demandes ouvertes, des commandes à payer ou des soldes de portefeuille. Traitez ces opérations avant de la désactiver.',
            ]);
        }
    }
}
