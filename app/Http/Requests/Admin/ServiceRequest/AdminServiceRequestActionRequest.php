<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;

class AdminServiceRequestActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('requests.manage') === true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
