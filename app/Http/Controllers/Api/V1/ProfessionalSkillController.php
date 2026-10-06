<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalSkill\AttachProfessionalSkillRequest;
use App\Http\Resources\ProfessionalSkillResource;
use App\Models\ProfessionalProfile;
use App\Models\Skill;
use App\Services\SkillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfessionalSkillController extends Controller
{
    private readonly SkillService $skillService;

    public function __construct(SkillService $skillService)
    {
        $this->skillService = $skillService;
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->professionalProfile($request);

        return response()->json([
            'data' => ProfessionalSkillResource::collection($profile->skills()->get()),
            'message' => 'Compétences du profil professionnel récupérées avec succès.',
            'meta' => [],
        ]);
    }

    public function store(AttachProfessionalSkillRequest $request): JsonResponse
    {
        $profile = $this->professionalProfile($request);
        $this->skillService->attach($profile->getKey(), $request->validated());

        $skill = Skill::findOrFail($request->integer('skill_id'));
        $professionalSkill = $profile->skills()->whereKey($skill->getKey())->firstOrFail();

        return response()->json([
            'data' => new ProfessionalSkillResource($professionalSkill),
            'message' => 'Compétence associée au profil professionnel avec succès.',
            'meta' => [],
        ], 201);
    }

    public function destroy(Request $request, Skill $skill): Response
    {
        $profile = $this->professionalProfile($request);
        $this->skillService->detach($profile->getKey(), $skill->getKey());

        return response()->noContent();
    }

    private function professionalProfile(Request $request): ProfessionalProfile
    {
        return $request->user()->professionalProfile()->firstOrFail();
    }
}
