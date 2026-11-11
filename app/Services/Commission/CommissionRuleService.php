<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\CommissionRule;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CommissionRuleService
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function currentRule(): CommissionRule
    {
        return $this->ruleForDate(now());
    }

    public function ruleForDate(\DateTimeInterface $date): CommissionRule
    {
        $rule = CommissionRule::query()
            ->where('effective_at', '<=', Carbon::instance($date))
            ->lockForUpdate()
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        return $rule ?? CommissionRule::query()->orderBy('effective_at')->orderBy('id')->firstOrFail();
    }

    public function rateForOrderDate(\DateTimeInterface $date): string
    {
        return (string) $this->ruleForDate($date)->rate;
    }

    public function changeRate(string $rate, User $actor, Request $request): CommissionRule
    {
        $rate = trim($rate);

        if (! preg_match('/^(?:0|[1-9]\d?)(?:\.\d{1,2})?$/', $rate)) {
            throw ValidationException::withMessages([
                'commission.rate' => 'Le taux doit être supérieur à 0 % et inférieur à 100 %, avec deux décimales au maximum.',
            ]);
        }

        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '');
        $basisPoints = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        if ($basisPoints <= 0 || $basisPoints >= 10000) {
            throw ValidationException::withMessages([
                'commission.rate' => 'Le taux doit être supérieur à 0 % et inférieur à 100 %, avec deux décimales au maximum.',
            ]);
        }

        $rate = ((int) $whole).'.'.str_pad(substr($fraction, 0, 2), 2, '0');

        return $this->database->transaction(function () use ($rate, $actor, $request): CommissionRule {
            $current = $this->currentRule();

            if ((string) $current->rate === $rate) {
                return $current;
            }

            $rule = CommissionRule::query()->create([
                'rate' => $rate,
                'effective_at' => now(),
                'created_by' => $actor->getAuthIdentifier(),
            ]);

            $this->auditLogs->recordEvent(
                action: 'admin.commission.rule.created',
                subject: $rule,
                user: $actor,
                metadata: ['commission_rate' => $rate, 'previous_rate' => (string) $current->rate],
                request: $request,
                category: 'administrative',
                reason: 'Création d’une nouvelle version de la règle de commission.',
            );

            return $rule;
        }, attempts: 3);
    }
}
