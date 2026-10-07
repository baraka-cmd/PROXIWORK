<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

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

    public function search(Request $request): View
    {
        $filters = $request->validate([
            'profession' => ['sometimes', 'string', 'max:120'],
            'search' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:180'],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180'],
            'skills_mode' => ['sometimes', 'in:any,all'],
            'city' => ['sometimes', 'string', 'max:120'],
            'province' => ['sometimes', 'string', 'max:120'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/i'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'availability' => ['sometimes', 'in:unknown,available,unavailable'],
            'verification' => ['sometimes', 'in:pending,under_review,verified,rejected'],
            'sort' => ['sometimes', 'in:relevance,rating,price_low,price_high,newest'],
        ]);

        $filters = $this->normalizeFilters($filters);
        $hasCriteria = $this->hasSearchCriteria($filters);

        return view('public.search', [
            'professionals' => $hasCriteria ? $this->searchService->search($filters) : null,
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function professionals(Request $request): View
    {
        $filters = $request->validate([
            'profession' => ['sometimes', 'string', 'max:120'],
            'search' => ['sometimes', 'string', 'max:120'],
            'category' => ['sometimes', 'string', 'max:180'],
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'max:180'],
            'skills_mode' => ['sometimes', 'in:any,all'],
            'city' => ['sometimes', 'string', 'max:120'],
            'province' => ['sometimes', 'string', 'max:120'],
            'min_price' => ['sometimes', 'numeric', 'min:0'],
            'max_price' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/i'],
            'rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'availability' => ['sometimes', 'in:unknown,available,unavailable'],
            'verification' => ['sometimes', 'in:pending,under_review,verified,rejected'],
            'sort' => ['sometimes', 'in:relevance,rating,price_low,price_high,newest'],
        ]);

        $filters = $this->normalizeFilters($filters);

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

    private function normalizeFilters(array $filters): array
    {
        foreach (['profession', 'search', 'category', 'city', 'province'] as $field) {
            if (isset($filters[$field])) {
                $filters[$field] = trim((string) $filters[$field]);
            }
        }

        if (isset($filters['currency'])) {
            $filters['currency'] = strtoupper(trim((string) $filters['currency']));
        }

        if (isset($filters['skills'])) {
            $filters['skills'] = collect($filters['skills'])
                ->map(fn ($skill): string => trim((string) $skill))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $filters;
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
