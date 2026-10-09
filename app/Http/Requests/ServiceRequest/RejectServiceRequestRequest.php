<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && $this->input('reason') !== null) {
            $this->merge(['reason' => trim((string) $this->input('reason'))]);
        }
    }
}
