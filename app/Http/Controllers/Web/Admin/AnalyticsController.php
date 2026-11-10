<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Analytics\AdminAnalyticsExportRequest;
use App\Http\Requests\Admin\Analytics\AdminAnalyticsIndexRequest;
use App\Services\Admin\Analytics\AdminAnalyticsService;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AdminAnalyticsService $analyticsService,
    ) {}

    public function index(AdminAnalyticsIndexRequest $request): View
    {
        $from = CarbonImmutable::parse($request->validated('from'))->startOfDay();
        $to = CarbonImmutable::parse($request->validated('to'))->endOfDay();

        return view('admin.analytics.index', [
            'analytics' => $this->analyticsService->get($from, $to),
            'from' => $from,
            'to' => $to,
            'range' => $request->validated('range', '30d'),
        ]);
    }

    public function export(AdminAnalyticsExportRequest $request): StreamedResponse
    {
        $from = CarbonImmutable::parse($request->validated('from'))->startOfDay();
        $to = CarbonImmutable::parse($request->validated('to'))->endOfDay();
        $analytics = $this->analyticsService->get($from, $to);
        $filename = 'proxiwork-analytics-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($analytics, $from, $to): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                return;
            }

            fwrite($output, chr(239).chr(187).chr(191));
            $rows = [
                ['Rapport PROXIWORK', 'Analytics'],
                ['Période du', $from->toDateString()],
                ['Période au', $to->toDateString()],
                ['Généré le', now()->toIso8601String()],
                [],
                ['Domaine', 'Indicateur', 'Valeur', 'Unité / devise'],
            ];

            foreach (['requests' => 'Demandes', 'quotations' => 'Devis', 'orders' => 'Commandes'] as $key => $label) {
                foreach (($analytics['commercial'][$key] ?? []) as $metric => $value) {
                    if (is_array($value)) {
                        foreach ($value as $status => $count) {
                            $rows[] = [$label, $metric.' / '.$status, $count, 'nombre'];
                        }
                    } elseif (is_numeric($value) || $value === null) {
                        $rows[] = [$label, $metric, $value ?? '', ''];
                    }
                }
            }

            foreach (($analytics['commercial']['conversion'] ?? []) as $metric => $value) {
                $rows[] = ['Conversion', $metric, $value, '%'];
            }

            foreach (($analytics['catalog']['categories'] ?? []) as $category) {
                $rows[] = ['Catégorie', $category['name'], 'Services: '.$category['services'].'; demandes: '.$category['requests'].'; commandes: '.$category['orders'], ''];
            }

            foreach (($analytics['financial']['refunds'] ?? []) as $currency => $refund) {
                $rows[] = ['Remboursements réussis', 'Montant', $refund['amount'], $currency];
                $rows[] = ['Remboursements réussis', 'Opérations', $refund['count'], 'nombre'];
            }

            foreach (($analytics['financial']['payment_volume'] ?? []) as $currency => $values) {
                $rows[] = ['Paiements réussis', 'Total de la période', array_sum($values), $currency];
            }

            foreach (($analytics['financial']['commissions'] ?? []) as $currency => $values) {
                $rows[] = ['Commissions comptabilisées', 'Total de la période', array_sum($values), $currency];
            }

            foreach (($analytics['geography']['professional_base_locations'] ?? []) as $location) {
                $rows[] = ['Zone professionnelle', $location['province'].' / '.$location['city'], 'Demandes: '.$location['requests'].'; commandes: '.$location['orders'], ''];
            }

            foreach ($rows as $row) {
                $safe = array_map(static function ($value): string {
                    $value = (string) $value;

                    return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'".$value : $value;
                }, $row);
                fputcsv($output, $safe, ';', '"', '\\');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
