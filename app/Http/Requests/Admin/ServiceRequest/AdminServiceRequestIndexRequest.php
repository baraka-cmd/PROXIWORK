<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminServiceRequestIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('requests.view') === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'professional_id' => ['nullable', 'integer', 'exists:professional_profiles,id'],
            'status' => ['nullable', Rule::in(['draft', 'requested', 'quoted', 'accepted', 'cancelled', 'rejected'])],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'requested_from' => ['nullable', 'date'],
            'requested_to' => ['nullable', 'date', 'after_or_equal:requested_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
