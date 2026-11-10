<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformSettingController extends Controller
{
    public function __construct(
        private readonly PlatformSettingService $settings,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => $this->settings->all(),
            'currencies' => ['USD', 'CDF'],
            'verificationStates' => ['pending', 'under_review', 'verified', 'rejected'],
            'integrations' => [
                'mail' => filled(config('mail.default')) && filled(config('mail.mailers.'.config('mail.default').'.transport')),
                'sms' => filled(config('services.twilio.account_sid')) && filled(config('services.twilio.auth_token')) && filled(config('services.twilio.from')),
                'push' => filled(config('services.firebase.project_id')) && filled(config('services.firebase.client_email')) && filled(config('services.firebase.private_key')),
                'storage' => filled(config('filesystems.default')),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->settings->update($request->input('settings', []), $request->user(), $request);

        return redirect()->route('admin.settings.index')->with('success', 'Les paramètres autorisés ont été enregistrés.');
    }
}
