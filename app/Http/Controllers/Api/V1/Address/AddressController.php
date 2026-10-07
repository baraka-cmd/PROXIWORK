<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Address;

use App\Http\Controllers\Controller;
use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\Address\AddressResource;
use App\Models\Address;
use App\Notifications\AccountActivityNotification;
use App\Services\Address\AddressService;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressService $addressService,
        private readonly AuditLogService $auditLogService,
    ) {}

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
            'message' => 'Adresses récupérées avec succès.',
            'meta' => [],
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
            'message' => 'Adresse créée avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(201);
    }

    public function show(Request $request, Address $address): AddressResource
    {
        $this->authorize('view', $address);

        return (new AddressResource($address))->additional([
            'message' => 'Adresse récupérée avec succès.',
            'meta' => [],
        ]);
    }

    public function update(UpdateAddressRequest $request, Address $address): AddressResource
    {
        $this->authorize('update', $address);

        $address->update($request->validated());

        return (new AddressResource($address->refresh()))->additional([
            'message' => 'Adresse mise à jour avec succès.',
            'meta' => [],
        ]);
    }

    public function setDefault(Request $request, Address $address): AddressResource
    {
        $this->authorize('setDefault', $address);

        $address = $this->addressService->setDefault($request->user(), $address);

        return (new AddressResource($address))->additional([
            'message' => 'Adresse définie comme adresse par défaut.',
            'meta' => [],
        ]);
    }

    public function destroy(Request $request, Address $address): Response
    {
        $this->authorize('delete', $address);

        $this->addressService->delete($request->user(), $address);

        $this->auditLogService->record('address_deleted', $address, $request->user(), [], $request);
        $request->user()->notify(new AccountActivityNotification(
            'Adresse supprimée',
            'Une adresse de votre compte a été supprimée.',
            'address_deleted',
        ));

        return response()->noContent();
    }
}
