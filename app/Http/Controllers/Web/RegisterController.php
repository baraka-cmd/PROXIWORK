<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\UserAccountStatus;
use App\Http\Requests\Web\RegisterRequest;
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
        $user = $database->transaction(function () use ($request): User {
            return User::create([
                'name' => trim($request->string('name')->toString()),
                'email' => strtolower(trim($request->string('email')->toString())),
                'password' => $request->string('password')->toString(),
                'account_status' => UserAccountStatus::ACTIVE,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(url('/'))->with(
            'status',
            'Votre compte PROXIWORK a été créé avec succès.'
        );
    }
}
