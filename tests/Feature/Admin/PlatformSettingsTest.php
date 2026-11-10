<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Settings\PlatformSettingService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_admin_can_view_and_update_whitelisted_operational_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Paramètres généraux');

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), [
                'settings' => [
                    'platform' => ['name' => 'PROXIWORK RDC', 'support_email' => 'support@example.test'],
                    'publication' => [
                        'minimum_description_length' => 35,
                        'maximum_title_length' => 140,
                        'maximum_images' => 6,
                    ],
                    'media' => ['maximum_image_kb' => 4096, 'maximum_document_kb' => 8192],
                    'notifications' => ['email_enabled' => 1, 'sms_enabled' => 0, 'push_enabled' => 0],
                ],
            ])
            ->assertRedirect(route('admin.settings.index'));

        $settings = app(PlatformSettingService::class);
        $this->assertSame('PROXIWORK RDC', $settings->get('platform.name'));
        $this->assertSame(35, $settings->get('publication.minimum_description_length'));
        $this->assertSame(6, $settings->get('publication.maximum_images'));
        $this->assertFalse($settings->get('notifications.sms_enabled'));
        $this->assertDatabaseHas('platform_settings', ['key' => 'publication.maximum_images']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.settings.updated']);
    }

    public function test_user_without_settings_permission_cannot_read_or_update_settings(): void
    {
        $this->seed(RbacSeeder::class);
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->put(route('admin.settings.update'), ['settings' => ['platform' => ['name' => 'Hacked']]])
            ->assertForbidden();
    }

    public function test_unknown_setting_keys_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'settings' => ['security' => ['disable_authorization' => true]],
            ])
            ->assertSessionHasErrors('settings');

        $this->assertDatabaseMissing('platform_settings', ['key' => 'security.disable_authorization']);
    }

    public function test_invalid_setting_values_are_rejected_without_partial_persistence(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.settings.index'))
            ->put(route('admin.settings.update'), [
                'settings' => [
                    'platform' => ['name' => 'Valid name', 'support_email' => 'not-an-email'],
                    'publication' => [
                        'minimum_description_length' => 5,
                        'maximum_title_length' => 140,
                        'maximum_images' => 6,
                    ],
                    'media' => ['maximum_image_kb' => 4096, 'maximum_document_kb' => 8192],
                    'notifications' => ['email_enabled' => 1, 'sms_enabled' => 0, 'push_enabled' => 0],
                ],
            ])
            ->assertSessionHasErrors('platform.support_email');

        $this->assertDatabaseMissing('platform_settings', ['key' => 'platform.name']);
    }
}
