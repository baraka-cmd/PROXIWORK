<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('services.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_cover' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('alt_text') && $this->input('alt_text') !== null) {
            $this->merge(['alt_text' => trim((string) $this->input('alt_text'))]);
        }
    }
}
