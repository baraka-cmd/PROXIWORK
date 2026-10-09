<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $image = $this->route('image');

        return $image !== null
            && $this->user()?->hasPermissionTo('services.manage') === true
            && $image->service->professionalProfile->user_id === $this->user()->getKey();
    }

    public function rules(): array
    {
        return [
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:160'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_cover' => ['sometimes', 'boolean'],
        ];
    }
}
