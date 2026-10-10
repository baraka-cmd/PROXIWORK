<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Http\Requests\Web\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, DatabaseManager $database): RedirectResponse
    {
        $data = $request->validated();
        $accountType = $data['account_type'];
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        $user = $database->transaction(function () use ($data, $accountType, $firstName, $lastName): User {
            // Public registration can assign only allow-listed public roles.
            // An administrator role is never accepted from browser input.
            $role = Role::query()->where('name', $accountType)->firstOrFail();

            $user = User::create([
                'name' => trim($firstName.' '.$lastName),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => $data['password'],
                'account_status' => UserAccountStatus::ACTIVE,
            ]);

            $user->assignRole($role);

            $user->profile()->create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => ($data['phone'] ?? '') !== '' ? $data['phone'] : null,
                'locale' => 'fr',
            ]);

            $user->notificationPreference()->create([
                'database_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
            ]);

            if ($accountType === 'professional') {
                $professionalProfile = $user->professionalProfile()->create();
                $professionalProfile->forceFill([
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
                ])->save();
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        if ($accountType === 'professional') {
            return redirect()->route('professional.dashboard')->with(
                'status',
                'Votre compte professionnel a été créé. Complétez votre dossier ; sa vérification reste en attente et vos services ne sont pas publiés automatiquement.'
            );
        }

        return redirect()->route('client.dashboard')->with(
            'status',
            'Votre compte client PROXIWORK a été créé avec succès.'
        );
    }
}
