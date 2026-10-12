<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Enums\PaymentMethod;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'transaction_status' => ['nullable', Rule::enum(PaymentTransactionStatus::class)],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'created_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:created_to'],
            'created_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:created_from'],
            'confirmed_from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:confirmed_to'],
            'confirmed_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:confirmed_from'],
            'q' => ['nullable', 'string', 'max:100'],
        ];
    }
}
