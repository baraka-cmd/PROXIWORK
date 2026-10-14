<?php

declare(strict_types=1);

namespace App\Http\Requests\Professional\Wallet;

use App\Enums\WalletTransactionDirection;
use App\Enums\WalletTransactionType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfessionalWalletFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge([
                'currency' => strtoupper(trim((string) $this->input('currency'))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'type' => ['sometimes', 'nullable', Rule::enum(WalletTransactionType::class)],
            'direction' => ['sometimes', 'nullable', Rule::enum(WalletTransactionDirection::class)],
            'transaction_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'date_from' => ['sometimes', 'nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $from = $this->input('date_from');
                $to = $this->input('date_to');

                if (! is_string($from) || ! is_string($to)) {
                    return;
                }

                $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);

                if ($start !== false && $end !== false && $start->diffInDays($end) > 366) {
                    $validator->errors()->add('date_to', 'La période de recherche ne peut pas dépasser 366 jours.');
                }
            },
        ];
    }
}
