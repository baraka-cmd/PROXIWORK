<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FavoriteResource;
use App\Models\Favorite;
use App\Models\ProfessionalProfile;
use App\Services\FavoriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class FavoriteController extends Controller
{
    public function __construct(
        private readonly FavoriteService $favoriteService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()
            ->favorites()
            ->whereHas('professionalProfile', fn ($profile) => $profile->publiclyDiscoverable())
            ->with(['professionalProfile.user.profile'])
            ->latest('id')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return FavoriteResource::collection($favorites)->additional([
            'message' => 'Vos favoris ont été récupérés avec succès.',
            'meta' => [],
        ]);
    }

    public function store(Request $request, ProfessionalProfile $professionalProfile): JsonResponse
    {
        $this->authorize('create', [Favorite::class, $professionalProfile]);

        [$favorite, $created] = $this->favoriteService->add(
            $request->user(),
            $professionalProfile
        );

        return (new FavoriteResource($favorite))->additional([
            'message' => $created
                ? 'Professionnel ajouté aux favoris.'
                : 'Professionnel déjà présent dans vos favoris.',
            'meta' => ['created' => $created],
        ])->response()->setStatusCode(
            $created ? Response::HTTP_CREATED : Response::HTTP_OK
        );
    }

    public function destroy(Request $request, ProfessionalProfile $professionalProfile): Response
    {
        $favorite = $request->user()
            ->favorites()
            ->where('professional_profile_id', $professionalProfile->getKey())
            ->first();

        if ($favorite !== null) {
            $this->authorize('delete', $favorite);
        }

        $this->favoriteService->remove($request->user(), $professionalProfile);

        return response()->noContent();
    }
}
