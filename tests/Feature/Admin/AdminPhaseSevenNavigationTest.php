<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FaqCategorySeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPhaseSevenNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_each_phase_seven_management_module(): void
    {
        $this->seed([RbacSeeder::class, FaqCategorySeeder::class]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        foreach ([
            'admin.notifications.deliveries.index',
            'admin.communication.templates.index',
            'admin.communication.announcements.index',
            'admin.communication.campaigns.index',
            'admin.communication.pages.index',
            'admin.communication.faq.index',
            'admin.communication.documents.index',
            'admin.communication.media.index',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
    }

    public function test_client_cannot_access_phase_seven_admin_modules(): void
    {
        $this->seed(RbacSeeder::class);

        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)
            ->get(route('admin.communication.pages.index'))
            ->assertForbidden();
    }
}
