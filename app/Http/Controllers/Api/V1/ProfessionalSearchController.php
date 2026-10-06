<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ProfessionalSearchRequest;
use App\Http\Resources\ProfessionalSearchResource;
use App\Services\ProfessionalSearch\ProfessionalSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfessionalSearchController
{
    public function __construct(
        private readonly ProfessionalSearchService $searchService,
    ) {
    }

    public function index(ProfessionalSearchRequest $request): AnonymousResourceCollection
    {
        $results = $this->searchService->search($request->validated());

        return ProfessionalSearchResource::collection($results)->additional([
            'message' => 'Professionnels récupérés avec succès.',
            'meta' => [
                'scope' => 'public',
                'sort' => $request->validated('sort', 'relevance'),
            ],
        ]);
    }
}
