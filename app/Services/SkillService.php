<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillService
{
    public function create(array $attributes): Skill
    {
        return DB::transaction(function () use ($attributes): Skill {
            $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $attributes['name']);
            return Skill::create($attributes);
        });
    }

    public function update(Skill $skill, array $attributes): Skill
    {
        return DB::transaction(function () use ($skill, $attributes): Skill {
            $skill->update($attributes);
            return $skill->refresh();
        });
    }

    public function archive(Skill $skill): void
    {
        DB::transaction(function () use ($skill): void {
            $skill->update(['status' => SkillStatus::ARCHIVED]);
        });
    }

    public function attach(int $professionalProfileId, array $attributes): void
    {
        DB::transaction(function () use ($professionalProfileId, $attributes): void {
            $skill = Skill::query()->lockForUpdate()->findOrFail($attributes['skill_id']);

            if ($skill->status !== SkillStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'skill_id' => 'Cette compétence n’est plus active.',
                ]);
            }

            if (DB::table('professional_skills')
                ->where('professional_profile_id', $professionalProfileId)
                ->where('skill_id', $skill->getKey())
                ->exists()) {
                throw ValidationException::withMessages([
                    'skill_id' => 'Cette compétence est déjà associée à ce profil professionnel.',
                ]);
            }

            DB::table('professional_skills')->insert([
                'professional_profile_id' => $professionalProfileId,
                'skill_id' => $skill->getKey(),
                'proficiency_level' => $attributes['proficiency_level'] ?? null,
                'years_experience' => $attributes['years_experience'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function detach(int $professionalProfileId, int $skillId): void
    {
        $deleted = DB::table('professional_skills')
            ->where('professional_profile_id', $professionalProfileId)
            ->where('skill_id', $skillId)
            ->delete();

        if ($deleted === 0) {
            throw ValidationException::withMessages([
                'skill_id' => 'Cette compétence n’est pas associée à ce profil professionnel.',
            ]);
        }
    }

    private function resolveSlug(?string $slug, string $name): string
    {
        $base = $slug !== null && $slug !== '' ? $slug : Str::slug($name);
        $candidate = $base;
        $suffix = 2;

        while (Skill::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
