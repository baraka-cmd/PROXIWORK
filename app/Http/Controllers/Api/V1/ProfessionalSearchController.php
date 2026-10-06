<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalSearchRequest;
use App\Http\Resources\ProfessionalSearchResource;
use App\Services\ProfessionalSearch\ProfessionalSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfessionalSearchController extends Controller
{
    public function __construct(
        private readonly ProfessionalSearchService $searchService
    ) {
    }

    public function index(ProfessionalSearchRequest $request): AnonymousResourceCollection
    {
        $professionals = $this->searchService->search($request->validated());

        return ProfessionalSearchResource::collection($professionals)->additional([
            'message' => 'Professionnels récupérés avec succès.',
            'meta' => [
                'scope' => 'public',
                'search' => true,
            ],
        ]);
    }
}
