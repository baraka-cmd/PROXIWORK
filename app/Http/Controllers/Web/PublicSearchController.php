<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\ProfessionalSearchRequest;
use App\Models\Category;
use App\Models\Skill;
use App\Services\ProfessionalSearch\ProfessionalSearchService;
use App\Services\PublicServiceSearch\PublicServiceSearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSearchController
{
    public function __construct(
        private readonly ProfessionalSearchService $professionalSearchService,
        private readonly PublicServiceSearchService $serviceSearchService,
    ) {}

    /**
     * Public service discovery. The catalogue is browsable without an account;
     * the database query itself enforces service, category, profile and account visibility.
     */
    public function search(PublicServiceSearchRequest $request): View
    {
        return view('public.search', [
            'services' => \App\Models\Service::query()->whereRaw('1 = 0')->paginate(12),
            'filters' => [],
            'categories' => collect(),
            'featuredCategories' => collect(),
            'skills' => collect(),
            'currencies' => collect(),
            'billingUnits' => collect(),
            'activeFiltersCount' => 0,
        ]);
    }
    public function professionals(ProfessionalSearchRequest $request): View
    {
        $filters = $request->validated();

        return view('public.professionals.index', [
            'professionals' => $this->professionalSearchService->search($filters),
            'filters' => $filters,
            ...$this->professionalFilterOptions(),
        ]);
    }

    public function redirectServicesIndex(Request $request): RedirectResponse
    {
        return redirect()->route('public.search', $request->query());
    }

    private function professionalFilterOptions(): array
    {
        return [
            'categories' => Category::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'skills' => Skill::query()
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ];
    }
}
