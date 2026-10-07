<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('client.profile.show', ['user' => $request->user()]);
    }

    public function edit(Request $request)
    {
        return view('client.profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required','string','max:120'],
            'phone' => ['nullable','string','max:30'],
        ]);

        $user->fill($data);
        $user->save();

        return redirect()->route('client.profile')->with('success', 'Profil mis à jour.');
    }
}