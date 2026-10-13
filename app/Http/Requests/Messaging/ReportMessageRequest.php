<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Enums\ReportReasonCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ReportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason_code' => ['required', new Enum(ReportReasonCode::class)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
