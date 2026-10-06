<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\StoreServiceImageRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceImageRequest;
use App\Http\Resources\ProfessionalService\ServiceImageResource;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Services\ProfessionalService\ProfessionalServiceManager;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalServiceImageController extends Controller
{
    public function __construct(
        private readonly ProfessionalServiceManager $serviceManager,
    ) {}

    public function store(StoreServiceImageRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);

        $image = $this->serviceManager->addImage(
            $service,
            $request->file('image'),
            $request->validated()
        );

        return (new ServiceImageResource($image))->additional([
            'message' => 'Image ajoutée avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateServiceImageRequest $request, ServiceImage $image): ServiceImageResource
    {
        $image = $this->serviceManager->updateImage($image, $request->validated());

        return (new ServiceImageResource($image))->additional([
            'message' => 'Image mise à jour avec succès.',
            'meta' => [],
        ]);
    }

    public function destroy(UpdateServiceImageRequest $request, ServiceImage $image): Response
    {
        $this->serviceManager->deleteImage($image);

        return response()->noContent();
    }
}
