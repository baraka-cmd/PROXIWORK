<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Verification;

use Illuminate\Foundation\Http\FormRequest;

class RequestProfessionalInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('admin.professionals.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('note') && $this->input('note') !== null) {
            $this->merge(['note' => trim((string) $this->input('note'))]);
        }
    }
}
