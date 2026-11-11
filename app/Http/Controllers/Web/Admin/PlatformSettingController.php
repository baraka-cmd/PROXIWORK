<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\CurrencyService;
use App\Services\Settings\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettingService $settings,
        private readonly CurrencyService $currencies,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->settings->all(),
            'currencies' => $this->currencies->all(),
            'integrations' => [
                'Paiement (simulation)' => [
                    'configured' => (bool) config('payment.simulation_enabled'),
                    'detail' => 'Aucune passerelle de paiement réelle n’est déclarée par cette configuration.',
                ],
                'E-mail' => [
                    'configured' => filled(config('mail.default')) && filled(config('mail.mailers.'.config('mail.default').'.transport')),
                    'detail' => 'Présence de paramètres du mailer, sans test réseau.',
                ],
                'SMS (Twilio)' => [
                    'configured' => filled(config('services.twilio.account_sid')) && filled(config('services.twilio.auth_token')) && filled(config('services.twilio.from')),
                    'detail' => 'Les identifiants restent dans l’environnement.',
                ],
                'Push (Firebase)' => [
                    'configured' => filled(config('services.firebase.project_id')) && filled(config('services.firebase.client_email')) && filled(config('services.firebase.private_key')),
                    'detail' => 'La clé privée n’est jamais affichée.',
                ],
                'Stockage' => [
                    'configured' => filled(config('filesystems.default')),
                    'detail' => 'Le disque privé des documents reste contrôlé par le code.',
                ],
            ],
        ]);
    }

    public function updateCurrency(Request $request, string $currency): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
        ]);

        $this->currencies->update($currency, (bool) $validated['is_active'], (bool) $validated['is_default'], $request->user(), $request);

        return redirect()->route('admin.settings.index')->with('success', 'La configuration de la devise a été enregistrée.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $this->settings->update($validated['settings'], $request->user(), $request);

        return redirect()->route('admin.settings.index')->with('success', 'Les paramètres autorisés ont été enregistrés.');
    }
}
