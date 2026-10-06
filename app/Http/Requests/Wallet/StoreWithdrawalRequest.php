<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') === true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'provider' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9_\-]+$/'],
            'destination' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency')),
        ]);
    }
}
