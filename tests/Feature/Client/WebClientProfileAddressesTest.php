<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebClientProfileAddressesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get('/client/profile')->assertRedirect('/login');
    }

    public function test_client_can_view_and_edit_profile(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)->get('/client/profile')->assertOk()->assertViewIs('client.profile.show');
        $this->actingAs($user)->patch('/client/profile', [
            'first_name' => 'Jean',
            'last_name' => 'Client',
            'phone' => '+243900000000',
            'bio' => 'Client PROXIWORK',
        ])->assertRedirect('/client/profile');

        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'first_name' => 'Jean',
            'last_name' => 'Client',
        ]);
    }

    public function test_non_client_is_forbidden_from_addresses(): void
    {
        $user = User::factory()->create();
        $user->assignRole('professional');

        $this->actingAs($user)->get('/client/addresses')->assertForbidden();
    }
}