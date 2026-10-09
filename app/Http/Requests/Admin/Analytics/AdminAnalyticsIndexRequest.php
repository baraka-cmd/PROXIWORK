<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminAnalyticsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('admin.dashboard.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'range' => ['nullable', 'in:7d,30d,90d,365d,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $range = $this->input('range', '30d');

        if ($range === 'custom') {
            return;
        }

        $days = match ($range) {
            '7d' => 7,
            '90d' => 90,
            '365d' => 365,
            default => 30,
        };

        $this->merge([
            'from' => now()->subDays($days - 1)->startOfDay()->toDateTimeString(),
            'to' => now()->endOfDay()->toDateTimeString(),
            'range' => $range,
        ]);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('from') || ! $this->filled('to')) {
                return;
            }

            $from = CarbonImmutable::parse($this->input('from'))->startOfDay();
            $to = CarbonImmutable::parse($this->input('to'))->endOfDay();

            if ($from->diffInDays($to) > 365) {
                $validator->errors()->add('to', 'La période analytique ne peut pas dépasser 366 jours.');
            }
        });
    }
}
