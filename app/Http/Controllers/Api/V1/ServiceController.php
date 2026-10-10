<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\IndexServiceRequest;
use App\Http\Resources\ProfessionalService\ServiceResource;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(IndexServiceRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $services = Service::query()
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
                'estimated_duration_minutes',
                'status',
                'sort_order',
                'published_at',
            ])
            ->published()
            ->whereHas('category', fn ($category) => $category->where('status', 'active'))
            ->whereHas('professionalProfile', function (Builder $profile): void {
                $profile
                    ->where('status', ProfessionalProfile::STATUS_ACTIVE)
                    ->where('visibility', ProfessionalProfile::VISIBILITY_PUBLIC)
                    ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                    ->whereHas('user', fn (Builder $user) => $user
                        ->where('account_status', UserAccountStatus::ACTIVE->value)
                        ->whereNotNull('email_verified_at'));
            })
            ->with([
                'category:id,name,slug',
                'skills:id,name,slug',
                'images:id,service_id,path,alt_text,sort_order,is_cover',
                'professionalProfile:id,user_id',
                'professionalProfile.user:id,name',
            ])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->when($validated['skill_id'] ?? null, fn ($query, int $skillId) => $query->whereHas('skills', fn ($skills) => $skills->whereKey($skillId)))
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ServiceResource::collection($services)->additional([
            'message' => 'Services publiés récupérés avec succès.',
            'meta' => ['scope' => 'public'],
        ]);
    }

    public function show(Service $service): ServiceResource
    {
        abort_unless(
            $service->status->value === 'published'
            && $service->published_at !== null
            && $service->category->status->value === 'active'
            && $service->professionalProfile->status === ProfessionalProfile::STATUS_ACTIVE
            && $service->professionalProfile->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $service->professionalProfile->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $service->professionalProfile->user->isActive()
            && $service->professionalProfile->user->hasVerifiedEmail(),
            404
        );

        return (new ServiceResource(
            $service->load(['category', 'skills', 'images', 'professionalProfile.user'])
        ))->additional([
            'message' => 'Service publié récupéré avec succès.',
            'meta' => ['scope' => 'public'],
        ]);
    }
}
