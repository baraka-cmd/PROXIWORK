<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use App\Enums\SupportTicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'category' => ['required', new Enum(SupportTicketCategory::class)],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
