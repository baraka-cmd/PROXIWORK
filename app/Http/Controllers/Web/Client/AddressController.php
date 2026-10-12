<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\AddressService;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Address::class);

        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('client.addresses.index', compact('addresses'));
    }

    public function create(): View
    {
        $this->authorize('create', Address::class);

        return view('client.addresses.form', ['address' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Address::class);
        $data = $this->validated($request);
        $address = $this->addressService->create($request->user(), $data);

        $this->auditLogService->record(
            'address_created',
            $address,
            $request->user(),
            ['is_default' => $address->is_default],
            $request,
        );

        return redirect()
            ->route('client.addresses.index')
            ->with('success', 'Adresse ajoutée avec succès.');
    }

    public function edit(Request $request, Address $address): View
    {
        $this->authorize('update', $address);

        return view('client.addresses.form', compact('address'));
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('update', $address);
        $updatedAddress = $this->addressService->update(
            $request->user(),
            $address,
            $this->validated($request),
        );

        $this->auditLogService->record(
            'address_updated',
            $updatedAddress,
            $request->user(),
            ['is_default' => $updatedAddress->is_default],
            $request,
        );

        return redirect()
            ->route('client.addresses.index')
            ->with('success', 'Adresse mise à jour avec succès.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('delete', $address);
        $wasDefault = $address->is_default;

        $this->addressService->delete($request->user(), $address);

        $this->auditLogService->record(
            'address_deleted',
            $address,
            $request->user(),
            ['was_default' => $wasDefault],
            $request,
        );

        return redirect()
            ->route('client.addresses.index')
            ->with('success', 'Adresse supprimée avec succès.');
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        $this->authorize('setDefault', $address);
        $address = $this->addressService->setDefault($request->user(), $address);

        $this->auditLogService->record(
            'address_default_changed',
            $address,
            $request->user(),
            ['is_default' => true],
            $request,
        );

        return redirect()
            ->route('client.addresses.index')
            ->with('success', 'Adresse principale définie avec succès.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $countryCode = $request->input('country_code');

        if (is_string($countryCode)) {
            $request->merge(['country_code' => strtoupper(trim($countryCode))]);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'min:1', 'max:100'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'commune' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'string', 'max:150'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'access_instructions' => ['nullable', 'string', 'max:5000'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $hasLatitude = array_key_exists('latitude', $data) && $data['latitude'] !== null;
        $hasLongitude = array_key_exists('longitude', $data) && $data['longitude'] !== null;

        if ($hasLatitude xor $hasLongitude) {
            throw ValidationException::withMessages([
                $hasLatitude ? 'longitude' : 'latitude' => 'La latitude et la longitude doivent être renseignées ensemble.',
            ]);
        }

        // HTML checkboxes are omitted when unchecked. Normalize that omission
        // explicitly so updates can intentionally uncheck the default option.
        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }
}
