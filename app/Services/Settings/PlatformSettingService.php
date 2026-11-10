<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlatformSettingService
{
    private const CACHE_KEY = 'platform.settings.v1';

    private const DEFINITIONS = [
        'platform.name' => ['default' => 'PROXIWORK', 'rules' => 'required|string|max:120'],
        'platform.support_email' => ['default' => '', 'rules' => 'nullable|email:rfc|max:190'],
        'publication.minimum_description_length' => ['default' => 20, 'rules' => 'required|integer|min:20|max:1000'],
        'publication.maximum_title_length' => ['default' => 160, 'rules' => 'required|integer|min:3|max:160'],
        'publication.maximum_images' => ['default' => 8, 'rules' => 'required|integer|min:1|max:8'],
        'media.maximum_image_kb' => ['default' => 5120, 'rules' => 'required|integer|min:100|max:10240'],
        'media.maximum_document_kb' => ['default' => 10240, 'rules' => 'required|integer|min:100|max:20480'],
        'notifications.email_enabled' => ['default' => true, 'rules' => 'required|boolean'],
        'notifications.sms_enabled' => ['default' => true, 'rules' => 'required|boolean'],
        'notifications.push_enabled' => ['default' => true, 'rules' => 'required|boolean'],
    ];

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): array {
            $values = [];

            foreach (self::DEFINITIONS as $key => $definition) {
                $values[$key] = $definition['default'];
            }

            foreach (PlatformSetting::query()->get(['key', 'value']) as $setting) {
                if (array_key_exists($setting->key, $values)) {
                    $values[$setting->key] = $setting->value;
                }
            }

            return $values;
        });
    }

    public function get(string $key): mixed
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new \InvalidArgumentException('Unknown platform setting key.');
        }

        return $this->all()[$key];
    }

    public function update(array $input, User $actor, Request $request): void
    {
        $flattened = Arr::dot($input);
        $unknown = array_diff(array_keys($flattened), array_keys(self::DEFINITIONS));

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => 'La requête contient un ou plusieurs paramètres non autorisés.',
            ]);
        }

        $rules = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $rules[$key] = $definition['rules'];
        }

        $validated = Arr::dot(Validator::make($input, $rules)->validate());
        $changes = [];

        foreach ($validated as $key => $value) {
            if ($key === 'notifications.email_enabled' || $key === 'notifications.sms_enabled' || $key === 'notifications.push_enabled') {
                $value = (bool) $value;
            }

            if (in_array($key, ['publication.minimum_description_length', 'publication.maximum_title_length', 'publication.maximum_images', 'media.maximum_image_kb', 'media.maximum_document_kb'], true)) {
                $value = (int) $value;
            }

            $changes[$key] = $value;
        }

        $this->database->transaction(function () use ($changes, $actor, $request): void {
            $changedKeys = [];

            foreach ($changes as $key => $value) {
                $setting = PlatformSetting::query()->where('key', $key)->lockForUpdate()->first();

                if ($setting !== null && $setting->value === $value) {
                    continue;
                }

                PlatformSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'updated_by' => $actor->getAuthIdentifier(),
                        'version' => ($setting?->version ?? 0) + 1,
                    ],
                );

                $changedKeys[] = $key;
            }

            if ($changedKeys !== []) {
                $this->auditLogs->recordEvent(
                    action: 'admin.settings.updated',
                    user: $actor,
                    metadata: ['changed_keys' => $changedKeys],
                    request: $request,
                    category: 'administrative',
                    reason: 'Modification des paramètres opérationnels autorisés.',
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
    }
}
