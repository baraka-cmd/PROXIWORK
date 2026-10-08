<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminServiceRequestIndexRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->hasPermissionTo('requests.view') === true; }

    public function rules(): array
    {
        return [
            'search' => ['nullable','string','max:120'],
            'status' => ['nullable',Rule::in(['draft','requested','quoted','accepted','cancelled','rejected'])],
            'service_id' => ['nullable','integer','exists:services,id'],
            'per_page' => ['nullable','integer','min:1','max:100'],
        ];
    }
}
