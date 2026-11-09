<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoryStatus;
use App\Enums\SkillStatus;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillService
{
    public function create(array $attributes): Skill
    {
        return DB::transaction(function () use ($attributes): Skill {
            $this->assertCategoryIsActive((int) $attributes['category_id']);
            $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $attributes['name']);

            return Skill::query()->create($attributes);
        });
    }

    public function update(Skill $skill, array $attributes): Skill
    {
        return DB::transaction(function () use ($skill, $attributes): Skill {
            $locked = Skill::query()->lockForUpdate()->findOrFail($skill->getKey());
            $categoryId = (int) ($attributes['category_id'] ?? $locked->category_id);

            if ($categoryId > 0) {
                $this->assertCategoryIsActive($categoryId);
            }

            if (array_key_exists('name', $attributes) || array_key_exists('slug', $attributes)) {
                $attributes['slug'] = $this->resolveSlug(
                    $attributes['slug'] ?? null,
                    $attributes['name'] ?? $locked->name,
                    $locked,
                );
            }

            $locked->update($attributes);

            return $locked->refresh()->load('category');
        });
    }

    public function archive(Skill $skill): void
    {
        DB::transaction(function () use ($skill): void {
            Skill::query()->lockForUpdate()->findOrFail($skill->getKey())
                ->update(['status' => SkillStatus::ARCHIVED]);
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

            if ($skill->category_id !== null) {
                $this->assertCategoryIsActive((int) $skill->category_id);
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

    private function assertCategoryIsActive(int $categoryId): void
    {
        $category = Category::query()->lockForUpdate()->find($categoryId);

        if ($category === null || $category->status !== CategoryStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'category_id' => 'La compétence doit être rattachée à une catégorie active.',
            ]);
        }
    }

    private function resolveSlug(?string $slug, string $name, ?Skill $ignore = null): string
    {
        $base = Str::slug(filled($slug) ? $slug : $name) ?: 'competence';
        $candidate = $base;
        $suffix = 2;

        while (Skill::query()
            ->where('slug', $candidate)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
