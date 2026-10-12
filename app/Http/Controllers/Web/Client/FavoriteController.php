<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Services\FavoriteService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favoriteService) {}

    public function index(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->query(), [
            'type' => ['nullable', Rule::in(['all', 'professionals', 'services'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['recent', 'oldest', 'name'])],
        ]);

        if ($validator->fails()) {
            return redirect()->route('client.favorites.index')->withErrors($validator);
        }

        $filters = $validator->validated();
        $client = $request->user();
        $baseQuery = Favorite::query()->where('user_id', $client->getKey());

        $stats = [
            'all' => (clone $baseQuery)->count(),
            'professionals' => (clone $baseQuery)->whereNotNull('professional_profile_id')->count(),
            'services' => (clone $baseQuery)->whereNotNull('service_id')->count(),
        ];

        $favorites = (clone $baseQuery)
            ->with([
                'professionalProfile.user.profile',
                'professionalProfile.services' => fn ($query) => $query->published(),
                'service.category',
                'service.skills',
                'service.images',
                'service.professionalProfile.user.profile',
            ])
            ->when(($filters['type'] ?? 'all') === 'professionals', fn (Builder $query) => $query->whereNotNull('professional_profile_id'))
            ->when(($filters['type'] ?? 'all') === 'services', fn (Builder $query) => $query->whereNotNull('service_id'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->whereHas('professionalProfile.user', fn (Builder $userQuery) => $userQuery->where('name', 'like', $like))
                        ->orWhereHas('professionalProfile', function (Builder $profileQuery) use ($like): void {
                            $profileQuery->where('professional_title', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('province', 'like', $like);
                        })
                        ->orWhereHas('service', function (Builder $serviceQuery) use ($like): void {
                            $serviceQuery->where('title', 'like', $like)
                                ->orWhere('short_description', 'like', $like)
                                ->orWhere('description', 'like', $like);
                        });
                });
            })
            ->when(($filters['sort'] ?? 'recent') === 'oldest', fn (Builder $query) => $query->orderBy('created_at')->orderBy('id'))
            ->when(($filters['sort'] ?? 'recent') === 'name', function (Builder $query): void {
                $query->orderByRaw('COALESCE(
                    (SELECT users.name FROM professional_profiles
                     INNER JOIN users ON users.id = professional_profiles.user_id
                     WHERE professional_profiles.id = favorites.professional_profile_id LIMIT 1),
                    (SELECT services.title FROM services WHERE services.id = favorites.service_id LIMIT 1)
                ) ASC');
            })
            ->when(($filters['sort'] ?? 'recent') === 'recent', fn (Builder $query) => $query->latest('id'))
            ->paginate(12)
            ->withQueryString();

        $favorites->getCollection()->each(function (Favorite $favorite): void {
            $favorite->setAttribute('target_available', $this->targetIsAvailable($favorite));
        });

        return view('client.favorites.index', compact('favorites', 'filters', 'stats'));
    }

    public function store(Request $request, ProfessionalProfile $professionalProfile): RedirectResponse
    {
        $this->authorize('create', [Favorite::class, $professionalProfile]);

        [, $created] = $this->favoriteService->add($request->user(), $professionalProfile);

        return back()->with(
            'success',
            $created ? 'Professionnel ajouté à vos favoris.' : 'Ce professionnel figure déjà dans vos favoris.'
        );
    }

    public function storeService(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('create', [Favorite::class, $service]);

        [, $created] = $this->favoriteService->addService($request->user(), $service);

        return back()->with(
            'success',
            $created ? 'Service ajouté à vos favoris.' : 'Ce service figure déjà dans vos favoris.'
        );
    }

    public function destroy(Request $request, Favorite $favorite): RedirectResponse
    {
        $this->authorize('delete', $favorite);
        $this->favoriteService->removeFavorite($request->user(), $favorite);

        return back()->with('success', 'Favori retiré de votre liste.');
    }

    private function targetIsAvailable(Favorite $favorite): bool
    {
        if ($favorite->professionalProfile !== null) {
            $professional = $favorite->professionalProfile;

            return $professional->status === ProfessionalProfile::STATUS_ACTIVE
                && $professional->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
                && $professional->verification_status === ProfessionalVerificationStatus::VERIFIED
                && $professional->user?->isActive() === true;
        }

        $service = $favorite->service;
        $professional = $service?->professionalProfile;

        return $service !== null
            && $service->status === ServiceStatus::PUBLISHED
            && $service->published_at !== null
            && $service->category?->status === CategoryStatus::ACTIVE
            && $professional !== null
            && $professional->status === ProfessionalProfile::STATUS_ACTIVE
            && $professional->visibility === ProfessionalProfile::VISIBILITY_PUBLIC
            && $professional->verification_status === ProfessionalVerificationStatus::VERIFIED
            && $professional->user?->isActive() === true;
    }
}
