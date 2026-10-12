<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientQuotationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(QuotationStatus::class)],
            'bucket' => ['nullable', Rule::in(['review', 'negotiating', 'accepted', 'history'])],
            'received_from' => ['nullable', 'date_format:Y-m-d'],
            'received_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:received_from'],
            'validity' => ['nullable', Rule::in(['valid', 'expiring', 'expired'])],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'deadline', 'amount_asc', 'amount_desc'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('q') && $this->input('q') !== null) {
            $data['q'] = trim((string) $this->input('q'));
        }

        if ($this->has('currency') && $this->input('currency') !== null) {
            $data['currency'] = strtoupper(trim((string) $this->input('currency')));
        }

        $this->merge($data);
    }
}
