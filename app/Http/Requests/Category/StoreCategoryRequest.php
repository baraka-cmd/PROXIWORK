<?php

declare(strict_types=1);

namespace App\Http\Requests\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('categories.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'slug' => ['nullable', 'string', 'min:2', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::enum(\App\Enums\CategoryStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            if ($parentId === null || ! $this->filled('parent_id')) {
                return;
            }

            $parent = Category::query()->find($parentId);
            if ($parent === null) {
                return;
            }

            if ($parent->parent_id !== null) {
                $validator->errors()->add('parent_id', 'La hiérarchie des catégories est limitée à deux niveaux.');
            }

            if ($parent->status !== CategoryStatus::ACTIVE) {
                $validator->errors()->add('parent_id', 'La catégorie parente doit être active.');
            }
        }];
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
