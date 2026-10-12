<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;

class SetServiceRequestAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') ?? false;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1', 'exists:addresses,id'],
        ];
    }
}
