<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminServiceIndexRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermissionTo('services.view') === true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable','string','max:120'],
            'status' => ['nullable',Rule::in(['draft','published','unpublished','archived'])],
            'category_id' => ['nullable','integer','exists:categories,id'],
            'pricing_type' => ['nullable',Rule::in(['fixed','from','range','quote'])],
            'professional_id' => ['nullable','integer','exists:professional_profiles,id'],
            'sort' => ['nullable',Rule::in(['-created_at','title'])],
            'per_page' => ['nullable','integer','min:1','max:100'],
        ];
    }
}
