<?php

declare(strict_types=1);

namespace App\Services\ProfessionalSearch;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Models\ProfessionalProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProfessionalSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = ProfessionalProfile::query()
            ->whereHas('services', function (Builder $services): void {
                $services
                    ->published()
                    ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'));
            })
            ->with([
                'user.profile',
                'user.addresses' => fn (Builder $addresses) => $addresses
                    ->where('is_default', true)
                    ->select([
                        'id',
                        'user_id',
                        'city',
                        'province',
                        'country_code',
                    ]),
                'skills' => fn (Builder $skills) => $skills
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
                ->orWhereHas('user.profile', function (Builder $profile) use ($like): void {
                    $profile
                        ->where('bio', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like);
                })
                ->orWhereHas('services', function (Builder $service) use ($like): void {
                    $service
                        ->published()
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

            $value = $filters[$field];

            $query->whereHas('user.addresses', fn (Builder $addresses) => $addresses
                ->where($field, $value));
        }
    }

    private function applyPrice(Builder $query, array $filters): void
    {
        if (
            ! array_key_exists('min_price', $filters)
            && ! array_key_exists('max_price', $filters)
            && empty($filters['currency'])
        ) {
            return;
        }

        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        $currency = $filters['currency'] ?? null;

        $query->whereHas('services', function (Builder $services) use ($min, $max, $currency): void {
            $services
                ->published()
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
        if (array_key_exists('rating', $filters)) {
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

        match ($sort) {
            'rating' => $query
                ->orderByDesc('rating_average')
                ->orderByDesc('rating_count')
                ->orderByDesc('id'),
            'price_low' => $query
                ->orderByRaw(
                    '(SELECT MIN(COALESCE(price, price_min)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL)',
                    [ServiceStatus::PUBLISHED->value]
                )
                ->orderByDesc('id'),
            'price_high' => $query
                ->orderByRaw(
                    '(SELECT MAX(COALESCE(price, price_max)) FROM services WHERE services.professional_profile_id = professional_profiles.id AND services.status = ? AND services.published_at IS NOT NULL)',
                    [ServiceStatus::PUBLISHED->value]
                )
                ->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query
                ->orderByRaw(
                    'CASE WHEN verification_status = ? THEN 1 ELSE 0 END DESC',
                    [ProfessionalVerificationStatus::VERIFIED->value]
                )
                ->orderByDesc('rating_average')
                ->orderByDesc('published_services_count')
                ->orderByDesc('id'),
        };
    }
}
