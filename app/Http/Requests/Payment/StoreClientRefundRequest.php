<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('client') === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
