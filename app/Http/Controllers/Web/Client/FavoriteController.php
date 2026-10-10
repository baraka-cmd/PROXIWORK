<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Services\FavoriteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    public function __construct(private readonly FavoriteService $favoriteService) {}

    public function index(Request $request): View
    {
        $favorites = Favorite::query()
            ->where('user_id', $request->user()->getKey())
            ->whereHas('professionalProfile', fn ($profile) => $profile->publiclyDiscoverable())
            ->with(['professionalProfile.user.profile'])
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('client.favorites.index', compact('favorites'));
    }

    public function store(Request $request, ProfessionalProfile $professionalProfile): RedirectResponse
    {
        $this->authorize('create', [Favorite::class, $professionalProfile]);

        $this->favoriteService->add($request->user(), $professionalProfile);

        return back()->with('success', 'Professionnel ajouté à vos favoris.');
    }

    public function destroy(Request $request, Favorite $favorite): RedirectResponse
    {
        $this->authorize('delete', $favorite);

        $this->favoriteService->remove($request->user(), $favorite->professionalProfile);

        return back()->with('success', 'Professionnel retiré de vos favoris.');
    }
}
