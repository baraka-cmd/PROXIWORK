<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Models\Category;
use App\Models\HomepageSlide;
use App\Models\ProfessionalProfile;
use App\Models\Review;
use App\Models\Service;
use App\Models\Skill;
use App\Enums\UserAccountStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class HomePageController
{
    public function index(): View
    {
        $visibleProfessional = function (Builder $query): void {
            $query
                ->where('status', ProfessionalProfile::STATUS_ACTIVE)
                ->where('visibility', ProfessionalProfile::VISIBILITY_PUBLIC)
                ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                ->whereHas('user', fn (Builder $user) => $user->where('account_status', UserAccountStatus::ACTIVE->value));
        };

        $publishedServices = function (Builder $query) use ($visibleProfessional): void {
            $query
                ->published()
                ->whereHas('category', fn (Builder $category) => $category->where('status', 'active'))
                ->whereHas('professionalProfile', $visibleProfessional);
        };

        $slides = HomepageSlide::query()->published()->get();
        $services = Service::query()
            ->where($publishedServices)
            ->with(['category:id,name,slug', 'images', 'professionalProfile.user:id,name', 'professionalProfile.user.profile:id,user_id,first_name,last_name'])
            ->latest('published_at')
            ->take(6)
            ->get();

        return view('public.home-dynamic', [
            'slides' => $slides,
            'heroServices' => $services->take(3),
            'categories' => Category::query()
                ->active()
                ->whereHas('services', $publishedServices)
                ->withCount(['services' => $publishedServices])
                ->orderByDesc('services_count')
                ->orderBy('name')
                ->take(8)
                ->get(['id', 'name', 'slug', 'description', 'icon', 'image_path']),
            'skills' => Skill::query()
                ->active()
                ->whereHas('professionalProfiles', $visibleProfessional)
                ->withCount(['professionalProfiles' => $visibleProfessional])
                ->orderByDesc('professional_profiles_count')
                ->orderBy('name')
                ->take(12)
                ->get(['id', 'name', 'slug']),
            'professionals' => ProfessionalProfile::query()
                ->where($visibleProfessional)
                ->whereHas('services', $publishedServices)
                ->with(['user:id,name', 'user.profile:id,user_id,first_name,last_name'])
                ->withCount(['services' => $publishedServices])
                ->orderByDesc('rating_average')
                ->orderByDesc('rating_count')
                ->take(6)
                ->get(),
            'services' => $services,
            'reviews' => Review::query()
                ->where('status', 'published')
                ->whereHas('professional', $visibleProfessional)
                ->with(['client:id,name', 'professional:id,user_id,professional_title'])
                ->latest('published_at')
                ->take(6)
                ->get(),
        ]);
    }
}