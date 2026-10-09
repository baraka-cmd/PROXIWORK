<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Moderation;

use App\Enums\ReportReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type' => [
                'required',
                'in:profile,professional_profile,service,message,review',
            ],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', new Enum(ReportReasonCode::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
