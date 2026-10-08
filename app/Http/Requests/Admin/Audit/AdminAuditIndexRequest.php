<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Audit;

use Illuminate\Foundation\Http\FormRequest;

class AdminAuditIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('audit.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:255'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'ip_address' => ['nullable', 'ip'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
