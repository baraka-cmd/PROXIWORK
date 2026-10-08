<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Enums\ModerationActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReportActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'action_type' => ['required', Rule::enum(ModerationActionType::class)],
            'reason_code' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
