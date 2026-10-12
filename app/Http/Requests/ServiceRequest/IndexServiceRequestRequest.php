<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ServiceRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexServiceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in([
                ...array_map(
                    static fn (ServiceRequestStatus $status): string => $status->value,
                    ServiceRequestStatus::cases(),
                ),
                'closed',
            ])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'professional_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:professional_profiles,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'exists:categories,id'],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['sometimes', 'nullable', Rule::in(['newest', 'oldest'])],
        ];
    }

    public function filters(): array
    {
        return collect($this->validated())
            ->only(['status', 'search', 'professional_id', 'category_id', 'from', 'to', 'sort'])
            ->all();
    }
}
