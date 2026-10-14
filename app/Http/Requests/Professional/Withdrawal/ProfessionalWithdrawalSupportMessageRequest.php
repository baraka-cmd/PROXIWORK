<?php

declare(strict_types=1);

namespace App\Http\Requests\Professional\Withdrawal;

use Illuminate\Foundation\Http\FormRequest;

class ProfessionalWithdrawalSupportMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') === true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:10', 'max:10000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body')),
        ]);
    }
}
