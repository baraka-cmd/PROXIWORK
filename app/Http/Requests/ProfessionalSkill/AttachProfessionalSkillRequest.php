<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalSkill;

use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AttachProfessionalSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('professional_skills.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'proficiency_level' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $skill = $this->filled('skill_id') ? Skill::find($this->integer('skill_id')) : null;
            if ($skill !== null && $skill->status->value !== 'active') {
                $validator->errors()->add('skill_id', 'Cette compétence n’est plus disponible à la sélection.');
            }
        }];
    }
}
