<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('profile');

        return view('client.profile.show', compact('user'));
    }

    public function edit(Request $request)
    {
        $user = $request->user()->load('profile');

        return view('client.profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        DB::transaction(function () use ($request, $data): void {
            $request->user()->profile()->updateOrCreate([], $data);
        });

        return redirect()->route('client.profile')->with('success', 'Profil mis à jour.');
    }
}
