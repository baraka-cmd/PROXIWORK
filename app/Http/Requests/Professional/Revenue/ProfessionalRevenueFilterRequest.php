<?php

declare(strict_types=1);

namespace App\Http\Requests\Professional\Revenue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfessionalRevenueFilterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('from') || $this->filled('to')) {
            $this->merge(['period' => 'custom']);
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->hasRole('professional');
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['7d', '30d', 'this_month', 'this_quarter', 'this_year', 'custom'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'currency' => ['nullable', 'regex:/^[A-Za-z]{3}$/'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::in(['all', 'posted', 'reversed'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('period') === 'custom') {
                if (! $this->filled('from')) {
                    $validator->errors()->add('from', 'La date de début est obligatoire pour une période personnalisée.');
                }

                if (! $this->filled('to')) {
                    $validator->errors()->add('to', 'La date de fin est obligatoire pour une période personnalisée.');
                }
            }
        });
    }

    public function filters(): array
    {
        $filters = $this->validated();
        $filters['period'] = $filters['period'] ?? '30d';
        $filters['status'] = $filters['status'] ?? 'all';
        $filters['currency'] = isset($filters['currency']) && $filters['currency'] !== ''
            ? strtoupper($filters['currency'])
            : null;

        return $filters;
    }
}
