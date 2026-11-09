<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Payment;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPaymentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('payments.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'transaction_status' => ['nullable', Rule::enum(PaymentTransactionStatus::class)],
            'refund_status_group' => ['nullable', Rule::in(['open'])],
            'transaction_from' => ['nullable', 'date'],
            'transaction_to' => ['nullable', 'date', 'after_or_equal:transaction_from'],
            'processed_from' => ['nullable', 'date'],
            'processed_to' => ['nullable', 'date', 'after_or_equal:processed_from'],
            'commission_status' => ['nullable', Rule::in(['posted', 'reversed'])],
            'commission_from' => ['nullable', 'date'],
            'commission_to' => ['nullable', 'date', 'after_or_equal:commission_from'],
            'provider' => ['nullable', Rule::enum(PaymentProvider::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
