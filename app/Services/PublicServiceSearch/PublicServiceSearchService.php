<?php

declare(strict_types=1);

namespace App\Services\PublicServiceSearch;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PublicServiceSearchService
{
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Service::query()
            ->published()
            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
            ->whereHas('professionalProfile', function (Builder $professional) {
                $professional
                    ->where('status', 'active')
                    ->where('visibility', 'public')
                    ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                    ->whereHas('user', fn (Builder $user) => $user->where('account_status', UserAccountStatus::ACTIVE->value));
            })
            ->with([
                'category:id,name,slug',
                'skills:id,name,slug',
                'images',
                'professionalProfile:id,user_id,professional_title,description,city,province,verification_status,rating_average,rating_count',
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name,avatar_path',
            ]);

        $this->applyText($query, $filters);
        $this->applyCategory($query, $filters);
        $this->applySkills($query, $filters);
        $this->applyLocation($query, $filters);
        $this->applyPrice($query, $filters);
        $this->applySort($query, $filters);

        return $query
            ->paginate($filters['per_page'] ?? 12)
            ->withQueryString();
    }

    public function findVisible(string|int $service): Service
    {
        return Service::query()
            ->published()
            ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
            ->whereHas('professionalProfile', function (Builder $professional) {
                $professional
                    ->where('status', 'active')
                    ->where('visibility', 'public')
                    ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                    ->whereHas('user', fn (Builder $user) => $user->where('account_status', UserAccountStatus::ACTIVE->value));
            })
            ->with(['category', 'skills', 'images', 'professionalProfile.user.profile'])
            ->whereKey($service)
            ->firstOrFail();
    }

    private function applyText(Builder $query, array $filters): void
    {
        if (empty($filters['search'])) {
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
                        ->orWhere('province', 'like', $like);
                });
        });
    }

    private function applyCategory(Builder $query, array $filters): void
    {
        if (! empty($filters['category'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('slug', $filters['category']));
        }
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

        $query->where(function (Builder $services) use ($min, $max, $currency): void {
            $services->where(function (Builder $priced) use ($min, $max, $currency): void {
                $priced->whereIn('pricing_type', [ServicePricingType::FIXED->value, ServicePricingType::FROM->value])
                    ->when($currency, fn (Builder $priced) => $priced->where('currency', $currency))
                    ->when($min !== null, fn (Builder $priced) => $priced->where('price', '>=', $min))
                    ->when($max !== null, fn (Builder $priced) => $priced->where('price', '<=', $max));
            })->orWhere(function (Builder $range) use ($min, $max, $currency): void {
                $range->where('pricing_type', ServicePricingType::RANGE->value)
                    ->when($currency, fn (Builder $range) => $range->where('currency', $currency))
                    ->when($min !== null, fn (Builder $range) => $range->where('price_max', '>=', $min))
                    ->when($max !== null, fn (Builder $range) => $range->where('price_min', '<=', $max));
            });
        });
    }

    private function applySort(Builder $query, array $filters): void
    {
        match ($filters['sort'] ?? 'relevance') {
            'price_low' => $query->orderByRaw('COALESCE(price, price_min) ASC')->orderByDesc('id'),
            'price_high' => $query->orderByRaw('COALESCE(price, price_max) DESC')->orderByDesc('id'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }
}