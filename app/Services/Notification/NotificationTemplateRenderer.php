<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class NotificationTemplateRenderer
{
    private const ALLOWED_VARIABLES = ['prenom', 'nom', 'email', 'plateforme'];

    public function render(string $key, User $recipient, string $fallbackTitle, string $fallbackBody): array
    {
        $template = NotificationTemplate::query()
            ->where('key', $key)
            ->where('status', 'active')
            ->where('channel', 'database')
            ->first();

        if ($template === null) {
            return ['title' => $fallbackTitle, 'body' => $fallbackBody, 'version' => null];
        }

        $profile = $recipient->profile;
        $values = [
            'prenom' => (string) ($profile?->first_name ?? ''),
            'nom' => (string) ($profile?->last_name ?? $recipient->name),
            'email' => (string) $recipient->email,
            'plateforme' => 'PROXIWORK',
        ];

        $declared = $template->variables ?? [];
        foreach ([$template->title, $template->body] as $content) {
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $content, $matches);
            foreach (array_unique($matches[1] ?? []) as $variable) {
                if (!in_array($variable, self::ALLOWED_VARIABLES, true) || !in_array($variable, $declared, true)) {
                    throw ValidationException::withMessages(['template' => 'Le modèle contient une variable non autorisée : '.$variable]);
                }
            }
        }

        $replace = static fn (string $content): string => preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $match): string => $values[$match[1]] ?? '',
            $content,
        ) ?? $content;

        return ['title' => $replace($template->title), 'body' => $replace($template->body), 'version' => $template->version];
    }

    public function validate(string $title, string $body, array $variables): void
    {
        $used = [];
        foreach ([$title, $body] as $content) {
            preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $content, $matches);
            $used = array_merge($used, $matches[1] ?? []);
        }

        foreach (array_unique($used) as $variable) {
            if (!in_array($variable, self::ALLOWED_VARIABLES, true) || !in_array($variable, $variables, true)) {
                throw ValidationException::withMessages(['body' => 'Variable inconnue ou non déclarée : '.$variable]);
            }
        }
    }
}
