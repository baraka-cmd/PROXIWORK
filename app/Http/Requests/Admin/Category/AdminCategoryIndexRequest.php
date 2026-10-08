<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Category;

use App\Enums\CategoryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminCategoryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('categories.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable','string','max:120'],
            'status' => ['nullable',Rule::enum(CategoryStatus::class)],
            'parent_id' => ['nullable','integer','exists:categories,id'],
            'sort' => ['nullable',Rule::in(['name','-name','sort_order','-sort_order','created_at','-created_at'])],
            'per_page' => ['nullable','integer','min:10','max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) $this->merge(['search'=>trim((string)$this->input('search'))]);
    }
}
