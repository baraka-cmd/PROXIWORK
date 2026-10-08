<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Support;

use Illuminate\Foundation\Http\FormRequest;

class AdminSupportCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('support.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
