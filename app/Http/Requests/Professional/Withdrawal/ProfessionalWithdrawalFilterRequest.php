<?php

declare(strict_types=1);

namespace App\Http\Requests\Professional\Withdrawal;

use App\Enums\WithdrawalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfessionalWithdrawalFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') === true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(array_map(
                static fn (WithdrawalStatus $status): string => $status->value,
                WithdrawalStatus::cases(),
            ))],
            'currency' => ['nullable', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $from = $this->input('date_from');
                $to = $this->input('date_to');

                if ($from === null || $from === '' || $to === null || $to === '') {
                    if (($from !== null && $from !== '') || ($to !== null && $to !== '')) {
                        $validator->errors()->add('date_to', 'Les deux dates sont obligatoires pour filtrer une période.');
                    }

                    return;
                }

                try {
                    $start = CarbonImmutable::createFromFormat('!Y-m-d', $from);
                    $end = CarbonImmutable::createFromFormat('!Y-m-d', $to);
                } catch (\Throwable) {
                    return;
                }

                if ($start !== false && $end !== false && $start->diffInDays($end) > 365) {
                    $validator->errors()->add('date_to', 'La période personnalisée ne peut pas dépasser 366 jours.');
                }
            },
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        return $validated;
    }
}
