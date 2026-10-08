<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Support;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminSupportAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('support.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::query()->find($value);
                    if (! $user || ! $user->isActive() || ! $user->hasPermissionTo('support.manage')) {
                        $fail('Le destinataire doit être un membre actif du support.');
                    }
                },
            ],
        ];
    }
}
