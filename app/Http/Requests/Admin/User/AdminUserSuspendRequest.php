<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserSuspendRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('suspend', $target) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason_code' => [
                'required',
                Rule::in([
                    'suspected_fraud',
                    'abusive_use',
                    'compromised_account',
                    'identity_misrepresentation',
                    'policy_violation',
                    'administrative_decision',
                    'other',
                ]),
            ],
            'duration' => ['required', Rule::in(['until_review', '7d', '30d', 'custom'])],
            'suspended_until' => ['exclude_unless:duration,custom', 'required', 'date', 'after:now'],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:reason_code,other'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.required' => 'Sélectionnez un motif de suspension.',
            'duration.required' => 'Précisez la durée de la suspension.',
            'suspended_until.required' => 'Précisez la date de fin de la suspension.',
            'suspended_until.after' => 'La date de fin doit être dans le futur.',
            'note.required_if' => 'Précisez le motif lorsque vous sélectionnez « Autre ».',
        ];
    }
}
