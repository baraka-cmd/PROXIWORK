<?php

declare(strict_types=1);

namespace App\Services\ProfessionalSearch;

use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProfessionalSearchService
{
    public function search(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        $query = ProfessionalProfile::query()
            ->select([
                'id',
                'user_id',
                'professional_title',
                'business_name',
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
                'created_at',
            ])
            ->publiclyVisible()
            ->whereHas('services', function (Builder $services): void {
                $services
                    ->published()
                    ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'));
            })
            ->with([
                'user:id,name',
                'user.profile:id,user_id,first_name,last_name,bio,avatar_path',
                'skills' => fn ($skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
            ])
            ->withCount([
                'services as published_services_count' => fn (Builder $services) => $services
                    ->published()
                    ->whereHas('category', fn (Builder $category) => $category->where('status', 'active')),
            ]);

        if ($viewer?->hasRole('client') === true) {
            $query->with([
                'favorites' => fn ($favorites) => $favorites
                    ->where('user_id', $viewer->getKey())
                    ->select(['id', 'user_id', 'professional_profile_id']),
            ]);
        }

        $this->applyProfession($query, $filters);
        $this->applyTextSearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applySkills($query, $filters);
        $this->applyLocation($query, $filters);
        $this->applyPrice($query, $filters);
        $this->applyRating($query, $filters);
        $this->applyAvailability($query, $filters);
        $this->applyVerification($query, $filters);

        $this->applySort($query, $filters);

        return $query
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    public function findVisible(ProfessionalProfile $professional, ?User $viewer = null): ProfessionalProfile
    {
        $query = ProfessionalProfile::query()
            ->whereKey($professional->getKey())
            ->publiclyVisible()
            ->select([
                'id',
                'user_id',
                'professional_title',
                'business_name',
                'description',
                'languages',
                'years_experience',
                'starting_price',
                'currency',
                'province',
                'city',
                'commune',
                'service_radius_km',
                'verification_status',
                'verified_at',
                'availability_status',
                'rating_average',
                'rating_count',
            ])
            ->with([
                'user:id,name',
                'user.profile:id,user_id,first_name,last_name,bio,avatar_path',
                'skills' => fn ($skills) => $skills->where('status', 'active'),
                'services' => fn ($services) => $services
                    ->published()
                    ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
                    ->select([
                        'id',
                        'professional_profile_id',
                        'category_id',
                        'slug',
                        'title',
                        'short_description',
                        'description',
                        'pricing_type',
                        'price',
                        'price_min',
                        'price_max',
                        'currency',
                        'execution_mode',
                        'published_at',
                    ])
                    ->with([
                        'category:id,name,slug',
                        'skills:id,name,slug',
                        'images:id,service_id,path,alt_text,is_cover',
                        'realizations' => fn ($realizations) => $realizations
                            ->where('status', 'approved')
                            ->where('participation_confirmed', true)
                            ->select(['id', 'service_id', 'caption', 'completed_at']),
                    ])
                    ->latest('published_at'),
                'reviews' => fn ($reviews) => $reviews
                    ->where('status', 'published')
                    ->select(['id', 'order_id', 'professional_id', 'rating', 'comment', 'status', 'published_at'])
                    ->with([
                        'response:id,review_id,professional_id,response,status',
                        'order:id,service_request_id',
                        'order.serviceRequest:id,service_id',
                        'order.serviceRequest.service:id,title',
                    ])
                    ->latest('published_at'),
            ]);

        if ($viewer?->hasRole('client') === true) {
            $query->with([
                'favorites' => fn ($favorites) => $favorites
                    ->where('user_id', $viewer->getKey())
                    ->select(['id', 'user_id', 'professional_profile_id']),
            ]);
        }

        return $query->firstOrFail();
    }

    private function applyProfession(Builder $query, array $filters): void
    {
        if (empty($filters['profession'])) {
            return;
        }

        $like = '%'.$filters['profession'].'%';

        $query->where('professional_title', 'like', $like);
    }

    private function applyTextSearch(Builder $query, array $filters): void
    {
        $term = $filters['search'] ?? null;

        if ($term === null || $term === '') {
            return;
        }

        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('professional_title', 'like', $like)
                ->orWhere('business_name', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('user.profile', function (Builder $profile) use ($like): void {
                    $profile
                        ->where('bio', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                })
                ->orWhereHas('services', function (Builder $service) use ($like): void {
                    $service
                        ->published()
                        ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
                        ->where(function (Builder $service) use ($like): void {
                            $service
                                ->where('title', 'like', $like)
                                ->orWhere('short_description', 'like', $like)
                                ->orWhere('description', 'like', $like);
                        });
                })
                ->orWhereHas('skills', fn (Builder $skills) => $skills
                    ->where('status', 'active')
                    ->where(function (Builder $skills) use ($like): void {
                        $skills
                            ->where('name', 'like', $like)
                            ->orWhere('slug', 'like', $like);
                    }));
        });
    }

    private function applyCategory(Builder $query, array $filters): void
    {
        if (empty($filters['category'])) {
            return;
        }

        $query->whereHas('services', function (Builder $services) use ($filters): void {
            $services
                ->published()
                ->whereHas('category', fn (Builder $category) => $category
                    ->where('status', 'active')
                    ->where('slug', $filters['category']));
        });
    }

    private function applySkills(Builder $query, array $filters): void
    {
        $skillSlugs = collect($filters['skills'] ?? [])->merge(
            $filters['skill'] ?? null
        )->filter()->unique()->values();

        if ($skillSlugs->isEmpty()) {
            return;
        }

        if (($filters['skills_mode'] ?? 'any') === 'any') {
            $query->whereHas('skills', fn (Builder $skills) => $skills
                ->where('status', 'active')
                ->whereIn('slug', $skillSlugs->all()));

            return;
        }

        foreach ($skillSlugs as $skillSlug) {
            $query->whereHas('skills', fn (Builder $skills) => $skills
                ->where('status', 'active')
                ->where('slug', $skillSlug));
        }
    }

    private function applyLocation(Builder $query, array $filters): void
    {
        foreach (['city', 'province'] as $field) {
            if (empty($filters[$field])) {
                continue;
            }

            $query->whereRaw(
                'LOWER('.$field.') = ?',
                [mb_strtolower($filters[$field])],
            );
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

        $query->whereHas('services', function (Builder $services) use ($min, $max, $currency): void {
            $services
                ->published()
                ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
                ->when($currency, fn (Builder $services) => $services->where('currency', $currency))
                ->where(function (Builder $services) use ($min, $max): void {
                    $services
                        ->where(function (Builder $services) use ($min, $max): void {
                            $services
                                ->whereIn('pricing_type', [
                                    ServicePricingType::FIXED->value,
                                    ServicePricingType::FROM->value,
                                ])
                                ->when($min !== null, fn (Builder $services) => $services->where('price', '>=', $min))
                                ->when($max !== null, fn (Builder $services) => $services->where('price', '<=', $max));
                        })
                        ->orWhere(function (Builder $services) use ($min, $max): void {
                            $services
                                ->where('pricing_type', ServicePricingType::RANGE->value)
                                ->when($min !== null, fn (Builder $services) => $services->where('price_max', '>=', $min))
                                ->when($max !== null, fn (Builder $services) => $services->where('price_min', '<=', $max));
                        });
                });
        });
    }

    private function applyRating(Builder $query, array $filters): void
    {
        if (($filters['rating'] ?? null) !== null) {
            $query->where('rating_average', '>=', $filters['rating']);
        }
    }

    private function applyAvailability(Builder $query, array $filters): void
    {
        if (! empty($filters['availability'])) {
            $query->where(
                'availability_status',
                $filters['availability']
            );
        }
    }

    private function applyVerification(Builder $query, array $filters): void
    {
        if (! empty($filters['verification'])) {
            $query->where(
                'verification_status',
                $filters['verification']
            );
        }
    }

    private function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'relevance';
        $currency = $filters['currency'] ?? null;

        match ($sort) {
            'rating' => $query
                ->orderByDesc('rating_average')
                ->orderByDesc('rating_count')
                ->orderByDesc('id'),
            'price_low' => $query
                ->orderByRaw(
                    'COALESCE((SELECT MIN(COALESCE(price, price_min)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL AND services.currency = ? AND services.pricing_type IN (?, ?, ?) AND EXISTS (SELECT 1 FROM categories WHERE categories.id = services.category_id AND categories.status = ?)), 9999999999.99)',
                    [
                        ServiceStatus::PUBLISHED->value,
                        $currency,
                        ServicePricingType::FIXED->value,
                        ServicePricingType::FROM->value,
                        ServicePricingType::RANGE->value,
                        'active',
                    ]
                )
                ->orderByDesc('id'),
            'price_high' => $query
                ->orderByRaw(
                    'COALESCE((SELECT MAX(COALESCE(price_max, price)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL AND services.currency = ? AND services.pricing_type IN (?, ?, ?) AND EXISTS (SELECT 1 FROM categories WHERE categories.id = services.category_id AND categories.status = ?)), 0)',
                    [
                        ServiceStatus::PUBLISHED->value,
                        $currency,
                        ServicePricingType::FIXED->value,
                        ServicePricingType::FROM->value,
                        ServicePricingType::RANGE->value,
                        'active',
                    ]
                )
                ->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $this->applyRelevanceSort($query, $filters),
        };
    }

    private function applyRelevanceSort(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['search'] ?? ''));
        if ($term === '') {
            $term = trim((string) ($filters['profession'] ?? ''));
        }
        $term = mb_strtolower($term);

        if ($term !== '') {
            $like = '%'.$term.'%';

            $query->orderByRaw(
                'CASE
                    WHEN LOWER(professional_title) = ? THEN 6
                    WHEN LOWER(professional_title) LIKE ? THEN 5
                    WHEN LOWER(business_name) = ? THEN 4
                    WHEN LOWER(business_name) LIKE ? THEN 3
                    WHEN EXISTS (
                        SELECT 1 FROM profiles
                        WHERE profiles.user_id = professional_profiles.user_id
                            AND (LOWER(profiles.first_name) LIKE ? OR LOWER(profiles.last_name) LIKE ?)
                    ) THEN 3
                    WHEN EXISTS (
                        SELECT 1 FROM professional_skills
                        INNER JOIN skills ON skills.id = professional_skills.skill_id
                        WHERE professional_skills.professional_profile_id = professional_profiles.id
                            AND skills.status = ?
                            AND (LOWER(skills.name) LIKE ? OR LOWER(skills.slug) LIKE ?)
                    ) THEN 2
                    WHEN EXISTS (
                        SELECT 1 FROM services
                        WHERE services.professional_profile_id = professional_profiles.id
                            AND services.status = ?
                            AND services.published_at IS NOT NULL
                            AND (LOWER(services.title) LIKE ? OR LOWER(services.short_description) LIKE ?)
                            AND EXISTS (
                                SELECT 1 FROM categories
                                WHERE categories.id = services.category_id AND categories.status = ?
                            )
                    ) THEN 2
                    ELSE 1
                END DESC',
                [
                    $term,
                    $like,
                    $term,
                    $like,
                    $like,
                    $like,
                    'active',
                    $like,
                    $like,
                    ServiceStatus::PUBLISHED->value,
                    $like,
                    $like,
                    'active',
                ]
            );
        }

        $query
            ->orderByDesc('rating_average')
            ->orderByDesc('rating_count')
            ->orderByDesc('published_services_count')
            ->orderByDesc('id');
    }
}
