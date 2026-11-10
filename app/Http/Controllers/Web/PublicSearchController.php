<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Requests\ProfessionalSearchRequest;
use App\Http\Requests\PublicServiceSearchRequest;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRealization;
use App\Models\Skill;
use App\Services\ProfessionalSearch\ProfessionalSearchService;
use App\Services\PublicServiceSearch\PublicServiceSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicSearchController
{
    public function __construct(
        private readonly ProfessionalSearchService $searchService,
        private readonly PublicServiceSearchService $serviceSearchService,
    ) {}

    public function search(PublicServiceSearchRequest $request): View
    {
        $filters = $request->validated();

        return view('public.search-services', [
            'services' => $this->serviceSearchService->search($filters, $request->user()),
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    public function service(Request $request, Service $service): View
    {
        $service = $this->serviceSearchService->findVisible($service->getKey(), $request->user());
        $service->loadMissing([
            'realizations' => fn ($query) => $query
                ->where('status', 'approved')
                ->where('participation_confirmed', true),
        ]);

        return view('public.services.show', compact('service'));
    }

    public function professionalPhoto(ProfessionalProfile $professional): BinaryFileResponse
    {
        // Reuse the public visibility rule before serving an image stored on the private disk.
        $professional = $this->searchService->findVisible($professional);
        $path = $professional->user->profile?->avatar_path;

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('local')->exists($path),
            404,
        );

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(
            is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $response = response()->file(Storage::disk('local')->path($path));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function professional(Request $request, ProfessionalProfile $professional): View
    {
        $professional = $this->searchService->findVisible($professional, $request->user());
        $favorite = $professional->relationLoaded('favorites')
            ? $professional->favorites->first()
            : null;
        $executionModes = $professional->services
            ->map(fn ($service) => $service->execution_mode)
            ->filter()
            ->unique()
            ->map(fn ($mode) => $mode->label())
            ->values();
        $realizations = $professional->services->flatMap(
            fn (Service $service) => $service->realizations->map(
                fn (ServiceRealization $realization): array => [
                    'service' => $service,
                    'realization' => $realization,
                ]
            )
        );

        return view('public.professionals.show', compact('professional', 'executionModes', 'favorite', 'realizations'));
    }

    public function professionals(ProfessionalSearchRequest $request): View
    {
        $filters = $request->validated();

        return view('public.professionals.index', [
            'professionals' => $this->searchService->search($filters, $request->user()),
            'filters' => $filters,
            ...$this->filterOptions(),
        ]);
    }

    private function filterOptions(): array
    {
        $requestableServices = fn ($services) => $services->requestable();

        return [
            'categories' => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->where(fn ($query) => $query
                    ->whereHas('services', $requestableServices)
                    ->orWhereHas('children', fn ($children) => $children
                        ->active()
                        ->whereHas('services', $requestableServices)))
                ->with(['children' => fn ($children) => $children
                    ->active()
                    ->whereHas('services', $requestableServices)
                    ->orderBy('sort_order')
                    ->orderBy('name')])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'sort_order']),
            'skills' => Skill::query()
                ->active()
                ->whereHas('services', fn ($services) => $services->requestable())
                ->orderBy('name')
                ->get(['id', 'category_id', 'name', 'slug']),
        ];
    }
}
