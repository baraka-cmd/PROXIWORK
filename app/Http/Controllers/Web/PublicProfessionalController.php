<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\UserAccountStatus;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicProfessionalController
{
    public function show(ProfessionalProfile $professionalProfile, ?string $slug = null): View|RedirectResponse
    {
        $professional = ProfessionalProfile::query()
            ->whereKey($professionalProfile->getKey())
            ->where('status', ProfessionalProfile::STATUS_ACTIVE)
            ->where('visibility', ProfessionalProfile::VISIBILITY_PUBLIC)
            ->whereHas('user', fn (Builder $user) => $user
                ->where('account_status', UserAccountStatus::ACTIVE->value))
            ->whereHas('services', fn (Builder $services) => $services->publiclyVisible())
            ->with([
                'user:id,name',
                'user.profile:id,user_id,first_name,last_name,avatar_path,bio',
                'skills' => fn (BelongsToMany $skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
            ])
            ->firstOrFail();

        $person = $professional->user->profile;
        $personName = $person !== null
            ? trim($person->first_name.' '.$person->last_name)
            : '';
        $displayName = filled($professional->business_name)
            ? $professional->business_name
            : (filled($personName) ? $personName : $professional->user->name);

        $profileSlug = Str::slug($displayName);
        if ($profileSlug === '') {
            $profileSlug = 'professionnel-'.$professional->getKey();
        }

        if ($slug !== $profileSlug) {
            return redirect()->route('public.professionals.show', [
                'professionalProfile' => $professional->getKey(),
                'slug' => $profileSlug,
            ], 301);
        }

        $services = Service::query()
            ->publiclyVisible()
            ->where('professional_profile_id', $professional->getKey())
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
                'skills' => fn (BelongsToMany $skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
                'images:id,service_id,path,alt_text,sort_order,is_cover',
                'professionalProfile' => fn (Builder $profile) => $profile->select([
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
                    'availability_status',
                    'rating_average',
                    'rating_count',
                    'status',
                    'visibility',
                ]),
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name,avatar_path',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('public.professionals.show', [
            'professional' => $professional,
            'person' => $person,
            'displayName' => $displayName,
            'profileSlug' => $profileSlug,
            'services' => $services,
        ]);
    }
}
