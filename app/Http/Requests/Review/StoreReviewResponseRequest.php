<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('professional') === true;
    }

    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }
}
