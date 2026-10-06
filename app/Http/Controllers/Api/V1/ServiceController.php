<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalService\IndexServiceRequest;
use App\Http\Resources\ProfessionalService\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceController extends Controller
{
    public function index(IndexServiceRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $services = Service::query()
            ->published()
            ->whereHas('category', fn ($category) => $category->where('status', 'active'))
            ->with(['category', 'skills', 'images', 'professionalProfile.user'])
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
            && $service->category->status->value === 'active',
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
