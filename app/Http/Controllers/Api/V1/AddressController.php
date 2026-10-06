<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Services\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Address::class);

        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $addresses = $request->user()
            ->addresses()
            ->orderByDesc('is_default')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return AddressResource::collection($addresses)->additional([
            'success' => true,
            'message' => 'Adresses récupérées avec succès.',
        ]);
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $this->authorize('create', Address::class);

        $address = $this->addressService->create(
            $request->user(),
            $request->validated(),
        );

        return (new AddressResource($address))->additional([
            'success' => true,
            'message' => 'Adresse créée avec succès.',
        ])->response()->setStatusCode(201);
    }

    public function show(Request $request, Address $address): AddressResource
    {
        $this->authorize('view', $address);

        return (new AddressResource($address))->additional([
            'success' => true,
            'message' => 'Adresse récupérée avec succès.',
        ]);
    }

    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        $this->authorize('update', $address);

        $address->update($request->validated());

        return (new AddressResource($address->refresh()))->additional([
            'success' => true,
            'message' => 'Adresse mise à jour avec succès.',
        ]);
    }

    public function setDefault(Request $request, Address $address): AddressResource
    {
        $this->authorize('setDefault', $address);

        $address = $this->addressService->setDefault($request->user(), $address);

        return (new AddressResource($address))->additional([
            'success' => true,
            'message' => 'Adresse définie comme adresse par défaut.',
        ]);
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorize('delete', $address);

        $this->addressService->delete($request->user(), $address);

        return response()->json([
            'success' => true,
            'message' => 'Adresse supprimée avec succès.',
        ]);
    }
}
