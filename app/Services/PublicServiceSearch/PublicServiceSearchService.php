<?php

declare(strict_types=1);

namespace App\Services\PublicServiceSearch;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicServiceSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Service::query()
            ->publiclyVisible()
            ->select([
                'id',
                'professional_profile_id',
                'category_id',
                'title',
                'slug',
                'short_description',
                'description',
                'pricing_type',
                'price',
                'price_min',
                'price_max',
                'currency',
                'billing_unit',
                'service_area',
                'estimated_duration_minutes',
                'status',
                'sort_order',
                'published_at',
                'created_at',
            ])
            ->with([
                'category:id,name,slug',
                'skills' => fn (Builder $skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
                'images:id,service_id,path,alt_text,sort_order,is_cover',
                'professionalProfile' => fn (Builder $professional) => $professional->select([
                    'id',
                    'user_id',
                    'professional_title',
                    'description',
                    'years_experience',
                    'starting_price',
                    'currency',
                    'province',
                    'city',
                    'commune',
                    'service_radius_km',
                    'verification_status',
                    'availability_status',
                    'rating_average',
                    'rating_count',
                    'status',
                    'visibility',
                ]),
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name',
            ]);

        $this->applyTextSearch($query, $filters);
        $this->applyProfession($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applySkills($query, $filters);
        $this->applyLocation($query, $filters);
        $this->applyPrice($query, $filters);
        $this->applyRating($query, $filters);
        $this->applyAvailability($query, $filters);
        $this->applyVerification($query, $filters);
        $this->applySort($query, $filters);

        return $query
            ->paginate($filters['per_page'] ?? 12)
            ->appends(collect($filters)->except('page')->all());
    }

    /**
     * Return active categories which actually have at least one public service.
     *
     * @return Collection<int, Category>
     */
    public function availableCategories(bool $featuredOnly = false): Collection
    {
        return Category::query()
            ->active()
            ->whereHas('services', fn (Builder $services) => $services->publiclyVisible())
            ->when($featuredOnly, fn (Builder $categories) => $categories->where('is_featured', true))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($featuredOnly ? 8 : 100)
            ->get(['id', 'name', 'slug', 'is_featured']);
    }

    /**
     * Only offer filters which can produce at least one public result.
     *
     * @return Collection<int, \App\Models\Skill>
     */
    public function availableSkills(): Collection
    {
        return \App\Models\Skill::query()
            ->active()
            ->whereHas('services', fn (Builder $services) => $services->publiclyVisible())
            ->orderBy('name')
            ->limit(100)
            ->get(['skills.id', 'skills.name', 'skills.slug']);
    }

    /**
     * Prices in different currencies are never mixed in a budget filter or price sort.
     *
     * @return Collection<int, string>
     */
    public function availableCurrencies(): Collection
    {
        return Service::query()
            ->publiclyVisible()
            ->whereNotNull('currency')
            ->where('currency', '!=', '')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency');
    }

    private function applyTextSearch(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['search'] ?? ''));

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('services.title', 'like', $like)
                ->orWhere('services.short_description', 'like', $like)
                ->orWhere('services.description', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category
                    ->where('status', 'active')
                    ->where('name', 'like', $like))
                ->orWhereHas('skills', fn (Builder $skills) => $skills
                    ->where('status', 'active')
                    ->where(function (Builder $skills) use ($like): void {
                        $skills->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    }))
                ->orWhereHas('professionalProfile', fn (Builder $professional) => $professional
                    ->where('professional_title', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $like))
                    ->orWhereHas('user.profile', fn (Builder $profile) => $profile
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)))
                ->orWhere('services.service_area', 'like', $like);
        });
    }

    private function applyProfession(Builder $query, array $filters): void
    {
        if (empty($filters['profession'])) {
            return;
        }

        $like = '%'.$filters['profession'].'%';
        $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
            ->where('professional_title', 'like', $like));
    }

    private function applyCategory(Builder $query, array $filters): void
    {
        if (empty($filters['category'])) {
            return;
        }

        $query->whereHas('category', fn (Builder $category) => $category
            ->where('status', 'active')
            ->where('slug', $filters['category']));
    }

    private function applySkills(Builder $query, array $filters): void
    {
        $skillSlugs = collect($filters['skills'] ?? [])->filter()->unique()->values();

        if ($skillSlugs->isEmpty()) {
            return;
        }

        if (($filters['skills_mode'] ?? 'any') === 'all') {
            foreach ($skillSlugs as $skillSlug) {
                $query->whereHas('skills', fn (Builder $skills) => $skills
                    ->where('status', 'active')
                    ->where('slug', $skillSlug));
            }

            return;
        }

        $query->whereHas('skills', fn (Builder $skills) => $skills
            ->where('status', 'active')
            ->whereIn('slug', $skillSlugs->all()));
    }

    private function applyLocation(Builder $query, array $filters): void
    {
        foreach (['city', 'province'] as $field) {
            $value = trim((string) ($filters[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $like = '%'.$value.'%';

            $query->where(function (Builder $query) use ($field, $value, $like): void {
                $query
                    ->whereHas('professionalProfile', fn (Builder $professional) => $professional
                        ->whereRaw('LOWER('.$field.') = ?', [mb_strtolower($value)])
                        ->orWhere($field, 'like', $like))
                    ->orWhere('services.service_area', 'like', $like);
            });
        }
    }

    private function applyPrice(Builder $query, array $filters): void
    {
        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        $currency = $filters['currency'] ?? null;

        if ($min === null && $max === null && empty($currency)) {
            return;
        }

        $query
            ->whereNotNull('services.currency')
            ->when($currency, fn (Builder $query) => $query->where('services.currency', $currency))
            ->where(function (Builder $query) use ($min, $max): void {
                $query
                    ->where(function (Builder $query) use ($min, $max): void {
                        $query
                            ->whereIn('services.pricing_type', [
                                ServicePricingType::FIXED->value,
                                ServicePricingType::FROM->value,
                            ])
                            ->when($min !== null, fn (Builder $query) => $query->where('services.price', '>=', $min))
                            ->when($max !== null, fn (Builder $query) => $query->where('services.price', '<=', $max));
                    })
                    ->orWhere(function (Builder $query) use ($min, $max): void {
                        $query
                            ->where('services.pricing_type', ServicePricingType::RANGE->value)
                            ->when($min !== null, fn (Builder $query) => $query->where('services.price_max', '>=', $min))
                            ->when($max !== null, fn (Builder $query) => $query->where('services.price_min', '<=', $max));
                    });
            });
    }

    private function applyRating(Builder $query, array $filters): void
    {
        if (! isset($filters['rating']) || $filters['rating'] === '') {
            return;
        }

        $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
            ->where('rating_count', '>', 0)
            ->where('rating_average', '>=', $filters['rating']));
    }

    private function applyAvailability(Builder $query, array $filters): void
    {
        if (! empty($filters['availability'])) {
            $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
                ->where('availability_status', $filters['availability']));
        }
    }

    private function applyVerification(Builder $query, array $filters): void
    {
        if (filter_var($filters['verified_only'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
                ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value));
        }
    }

    private function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'relevance';
        $term = trim((string) ($filters['search'] ?? ''));

        match ($sort) {
            'rating' => $query
                // Require a small review history before ranking a profile as highly rated.
                ->orderByRaw('(SELECT CASE WHEN rating_count >= 3 THEN 0 ELSE 1 END FROM professional_profiles WHERE professional_profiles.id = services.professional_profile_id) ASC')
                ->orderByRaw('(SELECT rating_average FROM professional_profiles WHERE professional_profiles.id = services.professional_profile_id) DESC')
                ->orderByRaw('(SELECT rating_count FROM professional_profiles WHERE professional_profiles.id = services.professional_profile_id) DESC')
                ->orderByDesc('services.published_at')
                ->orderByDesc('services.id'),
            'price_low' => $query
                ->orderByRaw('CASE WHEN COALESCE(services.price, services.price_min) IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByRaw('COALESCE(services.price, services.price_min) ASC')
                ->orderByDesc('services.published_at')
                ->orderByDesc('services.id'),
            'price_high' => $query
                ->orderByRaw('CASE WHEN COALESCE(services.price, services.price_max) IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByRaw('COALESCE(services.price, services.price_max) DESC')
                ->orderByDesc('services.published_at')
                ->orderByDesc('services.id'),
            'newest' => $query->orderByDesc('services.published_at')->orderByDesc('services.id'),
            default => $this->applyRelevanceSort($query, $term),
        };
    }

    private function applyRelevanceSort(Builder $query, string $term): void
    {
        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->orderByRaw(
                'CASE WHEN LOWER(services.title) = LOWER(?) THEN 0 WHEN LOWER(services.title) LIKE LOWER(?) THEN 1 ELSE 2 END ASC',
                [$term, $like]
            );
        }

        $query->orderByDesc('services.published_at')->orderByDesc('services.id');
    }
}
