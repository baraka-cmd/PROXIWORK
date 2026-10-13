<?php

declare(strict_types=1);

namespace App\Http\Requests\ProfessionalService;

use App\Models\ServiceImage;
use Illuminate\Foundation\Http\FormRequest;

class ReplaceServiceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $image = $this->route('image');

        return $image instanceof ServiceImage
            && $this->user()?->hasPermissionTo('services.manage') === true
            && $image->service->professionalProfile->user_id === $this->user()->getKey();
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'],
        ];
    }
}
