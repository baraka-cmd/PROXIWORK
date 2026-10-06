<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Http\Resources\Skill\SkillResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalSkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'skill' => new SkillResource($this->whenLoaded('skill')),
            'proficiency_level' => $this->pivot?->proficiency_level,
            'years_experience' => $this->pivot?->years_experience,
        ];
    }
}
