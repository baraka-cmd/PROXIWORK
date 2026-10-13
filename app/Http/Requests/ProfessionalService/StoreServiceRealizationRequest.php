<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRealizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            && $this->user()?->hasPermissionTo('services.manage') === true
            && $service->professionalProfile->user_id === $this->user()->getKey();
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
            'caption' => ['required', 'string', 'min:3', 'max:500'],
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'participation_confirmed' => ['required', 'accepted'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('caption') && $this->input('caption') !== null) {
            $this->merge(['caption' => trim((string) $this->input('caption'))]);
        }
    }
}
