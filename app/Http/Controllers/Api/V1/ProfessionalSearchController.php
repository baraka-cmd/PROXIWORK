<?php

declare(strict_types=1);

namespace AppHttpControllersApiV1;

use AppHttpRequestsProfessionalSearchRequest;
use AppHttpResourcesProfessionalSearchResource;
use AppServicesProfessionalSearchProfessionalSearchService;
use IlluminateHttpResourcesJsonAnonymousResourceCollection;

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
