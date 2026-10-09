<?php

declare(strict_types=1);

namespace App\Http\Requests\Skill;

use App\Enums\SkillStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('skills.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'min:2', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:skills,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(SkillStatus::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
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
