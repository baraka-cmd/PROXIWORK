<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\ProfessionalSearchRequest;
use App\Models\Category;
use App\Models\Skill;
use App\Services\ProfessionalSearch\ProfessionalSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicSearchController
{
    public function __construct(
        private readonly ProfessionalSearchService $searchService,
    ) {}

    public function search(ProfessionalSearchRequest $request): View
    {
        $filters = $request->validated();
        $hasCriteria = $this->hasSearchCriteria($filters);

        return view('public.search', [
            'professionals' => $hasCriteria ? $this->searchService->search($filters) : null,
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function professionals(ProfessionalSearchRequest $request): View
    {
        $filters = $request->validated();

        return view('public.professionals.index', [
            'professionals' => $this->searchService->search($filters),
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    private function filterOptions(): array
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

    private function filterOptions(): array
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

    private function hasSearchCriteria(array $filters): bool
    {
        foreach (['profession', 'search', 'category', 'skills', 'city', 'province', 'min_price', 'max_price', 'currency', 'rating', 'availability', 'verification'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== '' && $filters[$field] !== [] && $filters[$field] !== null) {
                return true;
            }
        }

        return false;
    }
}
