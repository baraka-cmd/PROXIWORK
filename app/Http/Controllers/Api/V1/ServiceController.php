<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\IndexServiceRequest;
use App\Http\Resources\ProfessionalService\ServiceResource;
use App\Models\Category;
use App\Models\Service;
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
                'execution_mode',
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
            ->requestable()
            ->with([
                'category:id,name,slug',
                'skills:id,name,slug',
                'images:id,service_id,path,alt_text,sort_order,is_cover',
                'professionalProfile:id,user_id',
                'professionalProfile.user:id,name',
            ])
            ->when(isset($validated['search']) && $validated['search'] !== '', function ($query) use ($validated): void {
                $search = (string) $validated['search'];

                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['category_id'] ?? null, function ($query, int $categoryId): void {
                $category = Category::query()->find($categoryId);

                if ($category?->parent_id === null) {
                    $query->whereHas('category', fn ($serviceCategory) => $serviceCategory
                        ->whereKey($categoryId)
                        ->orWhere('parent_id', $categoryId));
                } else {
                    $query->where('category_id', $categoryId);
                }
            })
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
        $service = Service::query()
            ->requestable()
            ->with(['category', 'skills', 'images', 'professionalProfile.user'])
            ->whereKey($service->getKey())
            ->firstOrFail();

        return (new ServiceResource(
            $service
        ))->additional([
            'message' => 'Service publié récupéré avec succès.',
            'meta' => ['scope' => 'public'],
        ]);
    }
}
