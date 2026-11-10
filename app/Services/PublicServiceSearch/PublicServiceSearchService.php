<?php

declare(strict_types=1);

namespace App\Services\PublicServiceSearch;

use App\Enums\ServicePricingType;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicServiceSearchService
{
    private const MINIMUM_REVIEWS_FOR_RATING = 3;

    public function search(array $filters, ?User $viewer = null): LengthAwarePaginator
    {
        $query = Service::query()
            ->requestable()
            ->with([
                'category:id,name,slug',
                'skills:id,name,slug',
                'images',
                'professionalProfile:id,user_id,professional_title,description,city,province,verification_status,verified_at,rating_average,rating_count',
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name,avatar_path',
            ]);

        if ($viewer?->hasRole('client') === true) {
            $query->with([
                'favorites' => fn (HasMany $favorites) => $favorites
                    ->where('user_id', $viewer->getKey()),
            ]);
        }

        $this->applyText($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applySkills($query, $filters);
        $this->applyLocation($query, $filters);
        $this->applyPrice($query, $filters);
        $this->applyPricingType($query, $filters);
        $this->applyRating($query, $filters);
        $this->applyAvailability($query, $filters);
        $this->applySort($query, $filters);

        return $query
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();
    }

    public function findVisible(string|int $service, ?User $viewer = null): Service
    {
        $query = Service::query()
            ->requestable()
            ->with(['category', 'skills', 'images', 'professionalProfile.user.profile'])
            ->whereKey($service);

        if ($viewer?->hasRole('client') === true) {
            $query->with([
                'favorites' => fn (HasMany $favorites) => $favorites
                    ->where('user_id', $viewer->getKey()),
            ]);
        }

        return $query->firstOrFail();
    }

    private function applyText(Builder $query, array $filters): void
    {
        if (! isset($filters['search']) || $filters['search'] === '') {
            return;
        }

        $like = '%'.$filters['search'].'%';
        $query->where(function (Builder $query) use ($like): void {
            $query
                ->where('title', 'like', $like)
                ->orWhere('short_description', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                ->orWhereHas('skills', fn (Builder $skill) => $skill->where('status', 'active')->where('name', 'like', $like))
                ->orWhereHas('professionalProfile', function (Builder $professional) use ($like): void {
                    $professional->where('professional_title', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('province', 'like', $like)
                        ->orWhereHas('user', fn (Builder $user) => $user
                            ->where('name', 'like', $like)
                            ->orWhereHas('profile', fn (Builder $profile) => $profile
                                ->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)));
                });
        });
    }

    private function applyCategory(Builder $query, array $filters): void
    {
        if (empty($filters['category'])) {
            return;
        }

        $category = Category::query()->active()->where('slug', $filters['category'])->first();

        if ($category === null) {
            return;
        }

        $query->whereHas('category', function (Builder $serviceCategory) use ($category): void {
            $serviceCategory->whereKey($category->getKey());

            if ($category->parent_id === null) {
                $serviceCategory->orWhere('parent_id', $category->getKey());
            }
        });
    }

    private function applySkills(Builder $query, array $filters): void
    {
        if (! empty($filters['skills'])) {
            foreach ($filters['skills'] as $skill) {
                $query->whereHas('skills', fn (Builder $skills) => $skills->where('status', 'active')->where('slug', $skill));
            }
        }
    }

    private function applyLocation(Builder $query, array $filters): void
    {
        $query->whereHas('professionalProfile', function (Builder $professional) use ($filters): void {
            foreach (['city', 'province'] as $field) {
                if (! empty($filters[$field])) {
                    $professional->whereRaw('LOWER('.$field.') = ?', [mb_strtolower($filters[$field])]);
                }
            }
        });
    }

    private function applyPrice(Builder $query, array $filters): void
    {
        $min = $filters['min_price'] ?? null;
        $max = $filters['max_price'] ?? null;
        $currency = $filters['currency'] ?? null;

        if ($min === null && $max === null && $currency === null) {
            return;
        }

        if ($min === null && $max === null) {
            $query->where('currency', $currency);

            return;
        }

        $query
            ->where('pricing_type', ServicePricingType::FIXED->value)
            ->where('currency', $currency)
            ->when($min !== null, fn (Builder $services) => $services->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $services) => $services->where('price', '<=', $max));
    }

    private function applyPricingType(Builder $query, array $filters): void
    {
        if (! empty($filters['pricing_type'])) {
            $query->where('pricing_type', $filters['pricing_type']);
        }
    }

    private function applyRating(Builder $query, array $filters): void
    {
        if (isset($filters['rating'])) {
            $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
                ->where('rating_average', '>=', $filters['rating'])
                ->where('rating_count', '>=', self::MINIMUM_REVIEWS_FOR_RATING));
        }
    }

    private function applyAvailability(Builder $query, array $filters): void
    {
        if (! empty($filters['availability'])) {
            $query->whereHas('professionalProfile', fn (Builder $professional) => $professional
                ->where('availability_status', $filters['availability']));
        }
    }

    private function applySort(Builder $query, array $filters): void
    {
        match ($filters['sort'] ?? 'relevance') {
            'price_low' => $query->where('pricing_type', ServicePricingType::FIXED->value)
                ->orderBy('price')->orderByDesc('id'),
            'price_high' => $query->where('pricing_type', ServicePricingType::FIXED->value)
                ->orderByDesc('price')->orderByDesc('id'),
            'rating' => $query
                ->orderBy(ProfessionalProfile::query()
                    ->selectRaw('CASE WHEN rating_count >= ? THEN 1 ELSE 0 END', [self::MINIMUM_REVIEWS_FOR_RATING])
                    ->whereColumn('professional_profiles.id', 'services.professional_profile_id'), 'desc')
                ->orderBy(ProfessionalProfile::query()
                    ->select('rating_average')
                    ->whereColumn('professional_profiles.id', 'services.professional_profile_id'), 'desc')
                ->orderBy(ProfessionalProfile::query()
                    ->select('rating_count')
                    ->whereColumn('professional_profiles.id', 'services.professional_profile_id'), 'desc')
                ->orderByDesc('services.id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $this->applyRelevanceSort($query, $filters),
        };
    }

    private function applyRelevanceSort(Builder $query, array $filters): void
    {
        $term = $filters['search'] ?? null;

        if (is_string($term) && $term !== '') {
            $like = '%'.$term.'%';
            $query->orderByRaw('CASE WHEN services.title LIKE ? THEN 1 ELSE 0 END DESC', [$like]);
        }

        $query->orderByDesc('services.published_at')->orderByDesc('services.id');
    }
}
