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
        $to = $this->filled('to') ? now()->parse($this->input('to')) : now();
        $from = $this->filled('from')
            ? now()->parse($this->input('from'))
            : $to->copy()->subDays(29)->startOfDay();

        $this->merge([
            'from' => $from->toDateTimeString(),
            'to' => $to->toDateTimeString(),
        ]);
    }
}
