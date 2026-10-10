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
            ->whereHas('professionalProfile', function ($profile): void {
                $profile
                    ->where('status', \App\Models\ProfessionalProfile::STATUS_ACTIVE)
                    ->where('visibility', \App\Models\ProfessionalProfile::VISIBILITY_PUBLIC)
                    ->where('verification_status', \App\Enums\ProfessionalVerificationStatus::VERIFIED->value)
                    ->whereHas('user', fn ($user) => $user
                        ->where('account_status', \App\Enums\UserAccountStatus::ACTIVE->value)
                        ->whereNotNull('email_verified_at'));
            })
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
