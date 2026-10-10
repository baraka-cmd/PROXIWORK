<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use App\Enums\CategoryStatus;
use App\Enums\SkillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('skills.manage') ?? false;
    }

    public function rules(): array
    {
        $skill = $this->route('skill');

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'slug' => ['sometimes', 'required', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('skills', 'slug')->ignore($skill?->getKey())],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(SkillStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'category_ids' => ['sometimes', 'array', 'min:1', 'max:20'],
            'category_ids.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->where('status', CategoryStatus::ACTIVE->value)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'slug', 'description', 'icon'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }
        if (array_key_exists('slug', $data)) {
            $data['slug'] = strtolower($data['slug']);
        }
        $this->merge($data);
    }
}
