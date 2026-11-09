<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dashboard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('admin.dashboard.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'string', Rule::in(['today', 'yesterday', '7d', '30d', '90d', 'this_month', 'last_month', 'this_year', 'custom'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $now = now();
        $period = $this->input('period', $this->filled('from') || $this->filled('to') ? 'custom' : '30d');
        $range = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            '90d' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            'custom' => null,
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        if ($range !== null) {
            $this->merge([
                'from' => $range[0]->toDateTimeString(),
                'to' => $range[1]->toDateTimeString(),
            ]);
        }

        $defaults = [];

        if ($period === 'custom' && ! $this->filled('from')) {
            $defaults['from'] = now()->subDays(29)->startOfDay()->toDateTimeString();
        }

        if ($period === 'custom' && ! $this->filled('to')) {
            $defaults['to'] = now()->endOfDay()->toDateTimeString();
        }

        $this->merge($defaults);

        if ($period === 'custom' && $this->filled('from') && $this->filled('to')) {
            $customFrom = (string) $this->input('from');
            $customTo = (string) $this->input('to');

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customFrom) === 1) {
                $this->merge(['from' => Carbon::parse($customFrom)->startOfDay()->toDateTimeString()]);
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customTo) === 1) {
                $this->merge(['to' => Carbon::parse($customTo)->endOfDay()->toDateTimeString()]);
            }
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('period') === 'custom'
                && $this->filled('from')
                && $this->filled('to')
                && ! $validator->errors()->has('from')
                && ! $validator->errors()->has('to')) {
                $from = Carbon::parse($this->input('from'));
                $to = Carbon::parse($this->input('to'));

                if ($from->diffInDays($to) > 365) {
                    $validator->errors()->add('to', 'La période personnalisée ne peut pas dépasser 366 jours.');
                }
            }
        });
    }
}
