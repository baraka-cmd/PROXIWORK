<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class AdminReportAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('reports.manage') ?? false;
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
                    if (! $user || ! $user->isActive() || ! $user->hasPermissionTo('reports.manage')) {
                        $fail('Le destinataire doit être un administrateur ou modérateur actif autorisé à traiter les signalements.');
                    }
                },
            ],
        ];
    }
}