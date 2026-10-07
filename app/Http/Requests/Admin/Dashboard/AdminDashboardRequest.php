<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class AdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('admin.dashboard.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('from') && !$this->filled('to')) {
            $this->merge([
                'from' => now()->subDays(29)->startOfDay()->toDateTimeString(),
                'to' => now()->endOfDay()->toDateTimeString(),
            ]);
        }
    }
}
