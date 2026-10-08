<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Enums\ReportPriority;
use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReportIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
            'priority' => ['nullable', Rule::enum(ReportPriority::class)],
            'target_type' => ['nullable', 'string', 'max:100'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'created_from' => ['nullable', 'date'],
            'created_to' => ['nullable', 'date', 'after_or_equal:created_from'],
            'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'priority', '-priority'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
