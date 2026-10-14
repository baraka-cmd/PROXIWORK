<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use App\Http\Requests\Professional\Revenue\ProfessionalRevenueFilterRequest;
use App\Models\Commission;
use App\Models\ProfessionalProfile;
use App\Models\WalletTransaction;
use App\Services\Professional\RevenueAnalyticsService;
use Illuminate\View\View;

class RevenueController extends Controller
{
    public function index(
        ProfessionalRevenueFilterRequest $request,
        RevenueAnalyticsService $analytics,
    ): View {
        $professional = $this->professionalProfile($request);
        $dashboard = $analytics->dashboard($professional, $request->filters());

        return view('professional.revenues.index', [
            ...$dashboard,
            'filters' => $request->filters(),
        ]);
    }

    public function show(
        ProfessionalRevenueFilterRequest $request,
        int $commission,
        RevenueAnalyticsService $analytics,
    ): View {
        $professional = $this->professionalProfile($request);
        $record = $analytics->findForProfessional($professional, $commission);

        $walletTransactions = WalletTransaction::query()
            ->where('reference_type', Commission::class)
            ->where('reference_id', $record->getKey())
            ->with('wallet')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('professional.revenues.show', [
            'commission' => $record,
            'walletTransactions' => $walletTransactions,
        ]);
    }

    public function export(
        ProfessionalRevenueFilterRequest $request,
        RevenueAnalyticsService $analytics,
    ) {
        $professional = $this->professionalProfile($request);
        $filters = $request->filters();
        $period = $analytics->periodDetails($filters);
        $query = $analytics->exportQuery($professional, $filters);

        return response()->streamDownload(function () use ($query, $filters, $period): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            // UTF-8 BOM helps spreadsheet software identify French text correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Référence',
                'Date de comptabilisation',
                'Commande',
                'Service',
                'Nature',
                'Montant brut',
                'Frais plateforme',
                'Montant net',
                'Devise',
                'État',
                'Date de correction',
            ], ',', '"', '');

            $query->chunk(500, function ($commissions) use ($output): void {
                foreach ($commissions as $commission) {
                    $serviceTitle = $commission->order?->items->first()?->service_title ?? 'Service indisponible';
                    $orderReference = $commission->order?->order_number
                        ?? ('#'.$commission->order_id);

                    fputcsv($output, [
                        'COM-'.$commission->getKey(),
                        $commission->posted_at?->toISOString(),
                        $this->safeCsvText($orderReference),
                        $this->safeCsvText($serviceTitle),
                        $commission->status->value === 'reversed' ? 'Commission annulée' : 'Revenu après commission',
                        (string) $commission->gross_amount,
                        (string) $commission->commission_amount,
                        (string) $commission->net_amount,
                        $commission->currency,
                        $commission->status->value,
                        $commission->reversed_at?->toISOString(),
                    ], ',', '"', '');
                }
            });

            fclose($output);
        }, 'proxiwork-revenus-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function professionalProfile(ProfessionalRevenueFilterRequest $request): ProfessionalProfile
    {
        return ProfessionalProfile::query()
            ->where('user_id', $request->user()->getKey())
            ->firstOrFail();
    }

    private function safeCsvText(string $value): string
    {
        // Prevent spreadsheet formula execution for user-controlled text columns.
        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }
}
