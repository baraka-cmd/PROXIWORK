<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'required_without:attachments', 'min:1', 'max:5000'],
            'idempotency_key' => ['nullable', 'uuid'],
            'reply_to_message_id' => ['nullable', 'integer', 'min:1', 'exists:messages,id'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,doc,docx',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required_without' => 'Écrivez un message ou joignez au moins un fichier.',
            'attachments.max' => 'Vous pouvez joindre au maximum cinq fichiers par message.',
            'attachments.*.mimes' => 'Formats autorisés : JPG, PNG, WebP, PDF, DOC et DOCX.',
            'attachments.*.max' => 'Chaque fichier doit faire au maximum 10 Mo.',
        ];
    }
}
