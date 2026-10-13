<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') ?? false;
    }

    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'min:20', 'max:5000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip', 'max:10240'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('summary') && is_string($this->input('summary'))) {
            $this->merge(['summary' => trim($this->input('summary'))]);
        }
    }
}
