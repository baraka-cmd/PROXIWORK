<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Enums\QuotationDurationUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'duration_value' => ['required', 'integer', 'min:1', 'max:10000'],
            'duration_unit' => ['required', Rule::enum(QuotationDurationUnit::class)],
            'conditions' => ['nullable', 'string', 'max:10000'],
            'valid_until' => ['required', 'date', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['currency', 'description', 'conditions'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }
        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }
        $this->merge($data);
    }
}
