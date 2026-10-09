<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Moderation;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:resolved,dismissed'],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
