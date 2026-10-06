<?php

declare(strict_types=1);

namespace App\Services\ProfessionalSearch;

use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ProfessionalSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;
        $skillIds = $filters['skill_ids'] ?? [];
        $skillsMode = $filters['skills_mode'] ?? 'any';
        $categoryId = $filters['category_id'] ?? null;

        $query = ProfessionalProfile::query()
            ->whereHas('services', fn (Builder $services) => $services
                ->published()
                ->whereHas('category', fn (Builder $category) => $category->where('status', 'active')))
            ->with([
                'user.profile',
                'user.addresses' => fn ($addresses) => $addresses
                    ->where('is_default', true)
                    ->limit(1),
                'skills' => fn ($skills) => $skills->where('status', 'active'),
                'services' => fn ($services) => $services
                    ->published()
                    ->with(['category', 'skills'])
                    ->orderBy('sort_order')
                    ->orderByDesc('published_at'),
            ]);

        $this->applyTextSearch($query, $search);
        $this->applyCategoryFilter($query, $categoryId);
        $this->applySkillFilter($query, $skillIds, $skillsMode);
        $this->applyLocationFilters($query, $filters);
        $this->applyPriceFilters($query, $filters);
        $this->applySorting($query, $filters['sort'] ?? 'relevance', $search);

        return $query
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    private function applyTextSearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $term = '%' . addcslashes($search, '%_\\') . '%';

        $query->where(function (Builder $professional) use ($term): void {
            $professional
                ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $term))
                ->orWhereHas('user.profile', fn (Builder $profile) => $profile
                    ->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('bio', 'like', $term))
                ->orWhereHas('services', fn (Builder $services) => $services
                    ->published()
                    ->where(function (Builder $service) use ($term): void {
                        $service
                            ->where('title', 'like', $term)
                            ->orWhere('short_description', 'like', $term)
                            ->orWhere('description', 'like', $term);
                    }))
                ->orWhereHas('skills', fn (Builder $skills) => $skills
                    ->where('status', 'active')
                    ->where(function (Builder $skill) use ($term): void {
                        $skill->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                    }))
                ->orWhereHas('services.category', fn (Builder $category) => $category
                    ->where('status', 'active')
                    ->where(function (Builder $category) use ($term): void {
                        $category->where('name', 'like', $term)->orWhere('slug', 'like', $term);
                    }));
        });
    }

    private function applyCategoryFilter(Builder $query, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        $query->whereHas('services', fn (Builder $services) => $services
            ->published()
            ->where('category_id', $categoryId)
            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active')));
    }

    private function applySkillFilter(Builder $query, array $skillIds, string $mode): void
    {
        if ($skillIds === []) {
            return;
        }

        if ($mode === 'all') {
            foreach ($skillIds as $skillId) {
                $query->where(function (Builder $professional) use ($skillId): void {
                    $professional
                        ->whereHas('skills', fn (Builder $skills) => $skills->whereKey($skillId)->where('skills.status', 'active'))
                        ->orWhereHas('services', fn (Builder $services) => $services
                            ->published()
                            ->whereHas('skills', fn (Builder $skills) => $skills->whereKey($skillId)));
                });
            }

            return;
        }

        $query->where(function (Builder $professional) use ($skillIds): void {
            $professional->whereHas('skills', fn (Builder $skills) => $skills->whereIn('skills.id', $skillIds)->where('skills.status', 'active'))
                ->orWhereHas('services', fn (Builder $services) => $services
                    ->published()
                    ->whereHas('skills', fn (Builder $skills) => $skills->whereIn('skills.id', $skillIds)->where('skills.status', 'active')));
        });
    }

    private function applyLocationFilters(Builder $query, array $filters): void
    {
        foreach (['city', 'province', 'country_code'] as $field) {
            if (($filters[$field] ?? null) === null || $filters[$field] === '') {
                continue;
            }

            $value = $filters[$field];

            $query->whereHas('user.addresses', fn (Builder $addresses) => $addresses
                ->where('is_default', true)
                ->whereRaw('LOWER(' . $field . ') = ?', [mb_strtolower($value)]));
        }
    }

    private function applyPriceFilters(Builder $query, array $filters): void
    {
        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        $currency = $filters['currency'] ?? null;

        if ($min === null && $max === null && $currency === null) {
            return;
        }

        $query->whereHas('services', function (Builder $services) use ($min, $max, $currency): void {
            $services
                ->published()
                ->when($currency, fn (Builder $service) => $service->where('currency', $currency))
                ->where(function (Builder $service) use ($min, $max): void {
                    $service
                        ->where(function (Builder $fixed) use ($min, $max): void {
                            $fixed->whereNotNull('price')
                                ->when($min !== null, fn (Builder $q) => $q->where('price', '>=', $min))
                                ->when($max !== null, fn (Builder $q) => $q->where('price', '<=', $max));
                        })
                        ->orWhere(function (Builder $range) use ($min, $max): void {
                            $range->whereNotNull('price_min')
                                ->whereNotNull('price_max')
                                ->when($min !== null, fn (Builder $q) => $q->where('price_max', '>=', $min))
                                ->when($max !== null, fn (Builder $q) => $q->where('price_min', '<=', $max));
                        });
                });
        });
    }

    private function applySorting(Builder $query, string $sort, ?string $search): void
    {
        if ($sort === 'price_low') {
            $query->orderByRaw(
                '(SELECT MIN(COALESCE(price, price_min)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL) ASC',
                [ServiceStatus::PUBLISHED->value]
            );

            return;
        }

        if ($sort === 'price_high') {
            $query->orderByRaw(
                '(SELECT MAX(COALESCE(price, price_max)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL) DESC',
                [ServiceStatus::PUBLISHED->value]
            );

            return;
        }

        if ($sort === 'newest') {
            $query->latest('professional_profiles.created_at');

            return;
        }

        if ($search !== null && $search !== '') {
            $term = '%' . addcslashes($search, '%_\\') . '%';

            $query->orderByRaw(
                'CASE
                    WHEN EXISTS (
                        SELECT 1 FROM services
                        WHERE services.professional_profile_id = professional_profiles.id
                        AND services.status = ?
                        AND services.published_at IS NOT NULL
                        AND services.title LIKE ?
                    ) THEN 1
                    WHEN EXISTS (
                        SELECT 1 FROM users
                        WHERE users.id = professional_profiles.user_id
                        AND users.name LIKE ?
                    ) THEN 2
                    ELSE 3
                END ASC',
                [
                    ServiceStatus::PUBLISHED->value,
                    $term,
                    $term,
                ]
            );
        }

        $query->orderBy('professional_profiles.id');
    }
}
