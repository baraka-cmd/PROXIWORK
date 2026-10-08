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
        $accountType = $request->validated('account_type');

        $user = $database->transaction(function () use ($request, $accountType): User {
            // The submitted value is allow-listed by RegisterRequest; roles are never
            // accepted as arbitrary model IDs or assigned from unvalidated input.
            $role = Role::query()->where('name', $accountType)->firstOrFail();

            $user = User::create([
                'name' => trim($request->string('name')->toString()),
                'email' => strtolower(trim($request->string('email')->toString())),
                'password' => $request->string('password')->toString(),
                'account_status' => UserAccountStatus::ACTIVE,
            ]);

            $user->assignRole($role);

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
                'Votre compte professionnel a été créé. Complétez votre profil ; sa vérification reste en attente.'
            );
        }

        return redirect()->route('client.dashboard')->with(
            'status',
            'Votre compte client PROXIWORK a été créé avec succès.'
        );
    }
}
