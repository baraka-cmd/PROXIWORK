<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Moderation;

use App\Enums\ModerationActionType;
use App\Enums\ReportReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ModerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action_type' => ['required', new Enum(ModerationActionType::class)],
            'reason_code' => ['nullable', new Enum(ReportReasonCode::class)],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
