<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Finance;

use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminFinanceFilterRequest extends FormRequest
{
    /**
     * Authorization is enforced by the dedicated permission middleware on
     * each route; this request is shared by read-only finance screens.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $statuses = array_values(array_unique(array_merge(
            array_map(static fn ($status): string => $status->value, PaymentStatus::cases()),
            array_map(static fn ($status): string => $status->value, PaymentTransactionStatus::cases()),
            array_map(static fn ($status): string => $status->value, CommissionStatus::cases()),
        )));

        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'provider' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/i'],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'wallet_status' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/i'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
