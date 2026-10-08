<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        return view('client.addresses.index', ['addresses' => $request->user()->addresses()->orderByDesc('is_default')->latest()->get()]);
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
            if (($data['is_default'] ?? false) === true || !$user->addresses()->exists()) {
                $user->addresses()->update(['is_default' => false]);
                $data['is_default'] = true;
            }
            $user->addresses()->create($data);
        });

        return redirect()->route('client.addresses.index')->with('success', 'Adresse ajoutée.');
    }

    public function edit(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        return view('client.addresses.form', compact('address'));
    }

    public function update(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $address, $data): void {
            if (($data['is_default'] ?? false) === true) {
                $request->user()->addresses()->whereKeyNot($address->getKey())->update(['is_default' => false]);
            }
            $address->update($data);
        });

        return redirect()->route('client.addresses.index')->with('success', 'Adresse mise à jour.');
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $request->user()->addresses()->latest('id')->first()?->update(['is_default' => true]);
        }

        return back()->with('success', 'Adresse supprimée.');
    }

    public function makeDefault(Request $request, Address $address)
    {
        $this->authorizeOwner($request, $address);

        DB::transaction(function () use ($request, $address): void {
            $request->user()->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return back()->with('success', 'Adresse principale définie.');
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless((int) $address->user_id === (int) $request->user()->getKey(), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'country_code' => ['required', 'string', 'size:2'],
            'province' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'commune' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['boolean'],
        ]);
    }
}