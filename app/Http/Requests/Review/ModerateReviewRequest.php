<?php

declare(strict_types=1);

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

class ModerateReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:published,hidden'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
