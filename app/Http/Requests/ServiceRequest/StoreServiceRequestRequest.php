<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceRequest;

use App\Models\Address;
use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') ?? false;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
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
            $service = Service::query()
                ->publiclyVisible()
                ->with('professionalProfile')
                ->find($this->integer('service_id'));

            if ($service === null) {
                $validator->errors()->add('service_id', 'Le service sélectionné n’est plus disponible.');
                return;
            }

            if ($service->status->value !== 'published' || $service->published_at === null) {
                $validator->errors()->add('service_id', 'Le service sélectionné n’est plus disponible.');
            }

            if ($this->filled('address_id')) {
                $owned = Address::query()
                    ->whereKey($this->integer('address_id'))
                    ->where('user_id', $this->user()?->getAuthIdentifier())
                    ->exists();

                if (!$owned) {
                    $validator->errors()->add('address_id', 'Cette adresse ne vous appartient pas.');
                }
            }

            $min = $this->input('budget_min');
            $max = $this->input('budget_max');

            if ($min !== null && $max !== null && (float) $max < (float) $min) {
                $validator->errors()->add('budget_max', 'Le budget maximum doit être supérieur ou égal au budget minimum.');
            }

            if (($min !== null || $max !== null) && $this->input('currency') === null) {
                $validator->errors()->add('currency', 'La devise est obligatoire lorsqu’un budget est indiqué.');
            }
        }];
    }
}
