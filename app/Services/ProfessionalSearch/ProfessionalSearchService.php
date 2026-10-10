<?php

declare(strict_types=1);

namespace App\Services\ProfessionalSearch;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ReviewStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProfessionalSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = ProfessionalProfile::query()
            ->select([
                'id',
                'user_id',
                'business_name',
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
                'verified_at',
                'availability_status',
                'rating_average',
                'rating_count',
                'created_at',
            ])
            ->publiclyDiscoverable()
            ->with([
                'user:id,name',
                'user.profile:id,user_id,first_name,last_name,avatar_path,bio',
                'skills' => fn ($skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
            ])
            ->withCount([
                'services as published_services_count' => fn (Builder $services) => $services
                    ->published()
                    ->whereHas('category', fn (Builder $category) => $category->where('status', 'active')),
            ]);

        $this->applyProfession($query, $filters);
        $this->applyTextSearch($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applySkills($query, $filters);
        $this->applyLocation($query, $filters);
        $this->applyPrice($query, $filters);
        $this->applyRating($query, $filters);
        $this->applyAvailability($query, $filters);
        $this->applyVerification($query, $filters);

        if (Auth::check() && Auth::user()?->hasRole('client')) {
            // Load only this client's favorite record. The identifier is needed by
            // the directory card to submit a real DELETE request when already saved.
            $query->with([
                'favorites' => fn ($favorites) => $favorites
                    ->where('user_id', Auth::id())
                    ->select(['favorites.id', 'favorites.user_id', 'favorites.professional_profile_id']),
            ]);
        }

        $this->applySort($query, $filters);

        return $query
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
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
        if (
            ! isset($filters['min_price'])
            && ! isset($filters['max_price'])
            && empty($filters['currency'])
        ) {
            return;
        }

        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        $currency = $filters['currency'] ?? null;
        $billingUnit = $filters['billing_unit'] ?? null;

        $query->whereHas('services', function (Builder $services) use ($min, $max, $currency, $billingUnit): void {
            $services
                ->published()
                ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
                ->when($currency, fn (Builder $services) => $services->where('currency', $currency))
                ->when($billingUnit, fn (Builder $services) => $services->where('billing_unit', $billingUnit))
                ->where(function (Builder $services) use ($min, $max): void {
                    $services
                        ->where(function (Builder $services) use ($min, $max): void {
                            $services
                                ->where('pricing_type', ServicePricingType::FIXED->value)
                                ->when($min !== null, fn (Builder $services) => $services->where('price', '>=', $min))
                                ->when($max !== null, fn (Builder $services) => $services->where('price', '<=', $max));
                        })
                        ->orWhere(function (Builder $services) use ($min, $max): void {
                            $services
                                ->where('pricing_type', ServicePricingType::FROM->value)
                                ->when($min !== null, fn (Builder $services) => $services->where('price_min', '>=', $min))
                                ->when($max !== null, fn (Builder $services) => $services->where('price_min', '<=', $max));
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
        if (isset($filters['rating'])) {
            $query
                ->where('rating_count', '>', 0)
                ->whereNotNull('rating_average')
                ->where('rating_average', '>=', $filters['rating']);
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
            $query
                ->where('verification_status', $filters['verification'])
                ->whereNotNull('verified_at');
        }
    }

    private function applySort(Builder $query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'relevance';

        match ($sort) {
            'rating' => $query
                // Confidence-weighted average: low-volume ratings move gradually toward the
                // platform-wide published-review average instead of dominating on one review.
                ->orderByRaw(
                    '((COALESCE(rating_average, 0) * rating_count) + (5 * COALESCE((SELECT AVG(rating) FROM reviews WHERE reviews.status = ? AND reviews.published_at IS NOT NULL), 0))) / (rating_count + 5) DESC',
                    [ReviewStatus::PUBLISHED->value]
                )
                ->orderByDesc('rating_count')
                ->orderByDesc('id'),
            'price_low' => $query
                ->orderByRaw(
                    '(SELECT MIN(CASE WHEN services.pricing_type = ? THEN services.price WHEN services.pricing_type IN (?, ?) THEN services.price_min ELSE NULL END) FROM services INNER JOIN categories ON categories.id = services.category_id WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL AND services.currency = ? AND services.billing_unit = ? AND categories.status = ?)',
                    [ServicePricingType::FIXED->value, ServicePricingType::FROM->value, ServicePricingType::RANGE->value, ServiceStatus::PUBLISHED->value, $filters['currency'] ?? '', $filters['billing_unit'] ?? '', 'active']
                )
                ->orderByDesc('id'),
            'price_high' => $query
                ->orderByRaw(
                    '(SELECT MAX(CASE WHEN services.pricing_type = ? THEN services.price WHEN services.pricing_type = ? THEN services.price_min WHEN services.pricing_type = ? THEN services.price_max ELSE NULL END) FROM services INNER JOIN categories ON categories.id = services.category_id WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL AND services.currency = ? AND services.billing_unit = ? AND categories.status = ?)',
                    [ServicePricingType::FIXED->value, ServicePricingType::FROM->value, ServicePricingType::RANGE->value, ServiceStatus::PUBLISHED->value, $filters['currency'] ?? '', $filters['billing_unit'] ?? '', 'active']
                )
                ->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $this->applyRelevanceSort($query, $filters),
        };
    }

    private function applyRelevanceSort(Builder $query, array $filters): void
    {
        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $exact = $term;
            $prefix = $term.'%';
            $contains = '%'.$term.'%';

            $query->orderByRaw(
                'CASE
                    WHEN professional_title = ? THEN 100
                    WHEN business_name = ? THEN 95
                    WHEN professional_title LIKE ? THEN 85
                    WHEN business_name LIKE ? THEN 80
                    WHEN professional_title LIKE ? THEN 70
                    WHEN business_name LIKE ? THEN 65
                    ELSE 0
                END DESC',
                [$exact, $exact, $prefix, $prefix, $contains, $contains]
            );

            $query->orderByRaw(
                'CASE WHEN verification_status = ? AND verified_at IS NOT NULL THEN 1 ELSE 0 END DESC',
                [ProfessionalVerificationStatus::VERIFIED->value]
            );
        } else {
            $query->orderByRaw(
                'CASE WHEN verification_status = ? AND verified_at IS NOT NULL THEN 1 ELSE 0 END DESC',
                [ProfessionalVerificationStatus::VERIFIED->value]
            );
        }

        if ($term !== '') {
            $query
                ->orderByDesc('rating_count')
                ->orderByDesc('rating_average')
                ->orderByDesc('published_services_count')
                ->orderByDesc('id');

            return;
        }

        $query
            ->orderByDesc('rating_average')
            ->orderByDesc('published_services_count')
            ->orderByDesc('id');
    }
}
