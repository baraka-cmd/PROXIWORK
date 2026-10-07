<?php

declare(strict_types=1);

namespace App\Services\Admin\Dashboard;

use App\Enums\CommissionStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Models\Commission;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function get(CarbonInterface $from, CarbonInterface $to): array
    {
        $usersTotal = User::query()->count();
        $usersNew = User::query()->whereBetween('created_at', [$from, $to])->count();

        $professionalsTotal = ProfessionalProfile::query()->count();
        $professionalsNew = ProfessionalProfile::query()->whereBetween('created_at', [$from, $to])->count();
        $verification = $this->statusCounts(ProfessionalProfile::class, $from, $to, 'verification_status');

        $clientsTotal = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'client'))->count();
        $clientsNew = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'client'))
            ->whereBetween('users.created_at', [$from, $to])
            ->count();

        $successfulTransactions = PaymentTransaction::query()
            ->where('status', PaymentTransactionStatus::SUCCEEDED->value)
            ->whereBetween('processed_at', [$from, $to]);

        $paymentVolume = $successfulTransactions
            ->select('currency', DB::raw('SUM(amount) AS total'))
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => number_format((float) $value, 2, '.', '')])
            ->all();

        $commissions = Commission::query()
            ->where('status', CommissionStatus::POSTED->value)
            ->whereBetween('posted_at', [$from, $to])
            ->select('currency', DB::raw('SUM(commission_amount) AS commission_total'), DB::raw('SUM(net_amount) AS net_total'))
            ->groupBy('currency')
            ->get()
            ->mapWithKeys(fn ($row): array => [(string) $row->currency => [
                'commission' => number_format((float) $row->commission_total, 2, '.', ''),
                'professional_net' => number_format((float) $row->net_total, 2, '.', ''),
            ]])
            ->all();

        return [
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'users' => ['total' => $usersTotal, 'new' => $usersNew],
            'professionals' => [
                'total' => $professionalsTotal,
                'new' => $professionalsNew,
                'verification' => [
                    'pending' => $verification[ProfessionalVerificationStatus::PENDING->value] ?? 0,
                    'under_review' => $verification[ProfessionalVerificationStatus::UNDER_REVIEW->value] ?? 0,
                    'verified' => $verification[ProfessionalVerificationStatus::VERIFIED->value] ?? 0,
                    'rejected' => $verification[ProfessionalVerificationStatus::REJECTED->value] ?? 0,
                ],
            ],
            'clients' => ['total' => $clientsTotal, 'new' => $clientsNew],
            'services' => $this->statusCounts(Service::class, $from, $to),
            'requests' => $this->statusCounts(ServiceRequest::class, $from, $to),
            'orders' => $this->statusCounts(Order::class, $from, $to),
            'payments' => $this->statusCounts(PaymentTransaction::class, $from, $to),
            'financial' => ['payment_volume' => $paymentVolume, 'commissions' => $commissions],
        ];
    }

    private function statusCounts(string $model, CarbonInterface $from, CarbonInterface $to, string $column = 'status'): array
    {
        return $model::query()
            ->whereBetween('created_at', [$from, $to])
            ->select($column, DB::raw('COUNT(*) AS aggregate'))
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->mapWithKeys(fn ($value, $key): array => [(string) $key => (int) $value])
            ->all();
    }
}
