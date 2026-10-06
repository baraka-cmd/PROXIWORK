<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceRequest;

use App\Models\Address;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') ?? false;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'title' => ['sometimes', 'string', 'min:3', 'max:160'],
            'description' => ['sometimes', 'string', 'min:20', 'max:10000'],
            'budget_min' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_max' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'desired_at' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['title', 'description', 'currency'] as $field) {
            if ($this->has($field) && $this->input($field) !== null) {
                $data[$field] = trim((string) $this->input($field));
            }
        }

        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $this->merge($data);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('address_id')) {
                $owned = Address::query()
                    ->whereKey($this->integer('address_id'))
                    ->where('user_id', $this->user()?->getAuthIdentifier())
                    ->exists();

                if (! $owned) {
                    $validator->errors()->add('address_id', 'Cette adresse ne vous appartient pas.');
                }
            }

            $request = $this->route('serviceRequest');

            if (! $request instanceof ServiceRequest) {
                return;
            }

            $min = array_key_exists('budget_min', $this->all())
                ? $this->input('budget_min')
                : $request->budget_min;
            $max = array_key_exists('budget_max', $this->all())
                ? $this->input('budget_max')
                : $request->budget_max;
            $currency = array_key_exists('currency', $this->all())
                ? $this->input('currency')
                : $request->currency;

            if ($min !== null && $max !== null && (float) $max < (float) $min) {
                $validator->errors()->add('budget_max', 'Le budget maximum doit être supérieur ou égal au budget minimum.');
            }

            if (($min !== null || $max !== null) && $currency === null) {
                $validator->errors()->add('currency', 'La devise est obligatoire lorsqu’un budget est indiqué.');
            }
        }];
    }
}
