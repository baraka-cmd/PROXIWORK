<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\Service;
use Illuminate\View\View;

class PublicServiceController
{
    public function show(Service $service): View
    {
        $service = Service::query()
            ->publiclyVisible()
            ->whereKey($service->getKey())
            ->with([
                'category:id,name,slug,description',
                'skills' => fn ($skills) => $skills
                    ->where('status', 'active')
                    ->select(['skills.id', 'skills.name', 'skills.slug']),
                'images:id,service_id,path,alt_text,sort_order,is_cover',
                'professionalProfile' => fn ($professional) => $professional->select([
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
                ]),
                'professionalProfile.user:id,name',
                'professionalProfile.user.profile:id,user_id,first_name,last_name,avatar_path,bio',
            ])
            ->firstOrFail();

        return view('public.services.show', [
            'service' => $service,
            'coverImage' => $service->images->firstWhere('is_cover', true)
                ?? $service->images->first(),
        ]);
    }
}
