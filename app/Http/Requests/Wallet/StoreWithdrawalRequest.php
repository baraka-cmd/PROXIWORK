<?php

declare(strict_types=1);

namespace App\Http\Requests\Wallet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // These are requested payout channels for manual review, not claims
            // that an external payout provider has been integrated.
            'provider' => ['required', 'string', Rule::in(['mobile_money', 'bank_transfer'])],
            'destination' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'confirm_request' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper(trim((string) $this->input('currency'))),
            'provider' => strtolower(trim((string) $this->input('provider'))),
            'destination' => trim((string) $this->input('destination')),
        ]);
    }
}
