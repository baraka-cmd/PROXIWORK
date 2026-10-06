<?php

declare(strict_types=1);

namespace App\Http\Requests\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('categories.manage') ?? false;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id', Rule::notIn([$category?->getKey()])],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'slug' => [
                'sometimes', 'required', 'string', 'min:2', 'max:140',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('categories', 'slug')->ignore($category?->getKey()),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:100'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(CategoryStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['name', 'slug', 'description', 'icon', 'image_path'] as $field) {
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
