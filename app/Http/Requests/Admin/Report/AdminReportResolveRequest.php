<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use Illuminate\Foundation\Http\FormRequest;

class AdminReportResolveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'max:2000'],
        ];
    }
}