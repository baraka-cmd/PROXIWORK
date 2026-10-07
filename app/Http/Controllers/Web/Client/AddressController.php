<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('client.addresses.index', ['addresses' => $request->user()->addresses()->latest()->get()]);
    }

    public function create()
    {
        return view('client.addresses.form', ['address' => null]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        DB::transaction(function () use ($user, $data): void {
            if (($data['is_default'] ?? false) === true) {
                $user->addresses()->update(['is_default' => false]);
            }
            $user->addresses()->create($data);
        });

        return redirect()->route('client.addresses.index')->with('success', 'Adresse ajoutée.');
    }

    public function edit(Request $request, $address)
    {
        abort_unless($address->user_id === $request->user()->getKey(), 403);
        return view('client.addresses.form', compact('address'));
    }

    public function update(Request $request, $address)
    {
        abort_unless($address->user_id === $request->user()->getKey(), 403);
        $data = $this->validated($request);
        DB::transaction(function () use ($request, $address, $data): void {
            if (($data['is_default'] ?? false) === true) {
                $request->user()->addresses()->whereKeyNot($address->getKey())->update(['is_default' => false]);
            }
            $address->update($data);
        });
        return redirect()->route('client.addresses.index')->with('success', 'Adresse mise à jour.');
    }

    public function destroy(Request $request, $address)
    {
        abort_unless($address->user_id === $request->user()->getKey(), 403);
        $address->delete();
        return back()->with('success', 'Adresse supprimée.');
    }

    public function makeDefault(Request $request, $address)
    {
        abort_unless($address->user_id === $request->user()->getKey(), 403);
        DB::transaction(function () use ($request, $address): void {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });
        return back()->with('success', 'Adresse principale définie.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required','string','max:80'],
            'recipient_name' => ['required','string','max:120'],
            'phone' => ['nullable','string','max:30'],
            'address_line_1' => ['required','string','max:255'],
            'address_line_2' => ['nullable','string','max:255'],
            'neighborhood' => ['nullable','string','max:120'],
            'commune' => ['nullable','string','max:120'],
            'city' => ['required','string','max:120'],
            'province' => ['required','string','max:120'],
            'country' => ['required','string','max:120'],
            'is_default' => ['boolean'],
        ]);
    }
}