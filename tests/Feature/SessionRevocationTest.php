<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_existing_web_session_is_rejected_after_session_version_changes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user)
            ->withSession(['auth.session_version' => 0])
            ->get(route('dashboard'))
            ->assertRedirect(route('client.dashboard'));

        app(SessionRevocationService::class)->revokeAll($user);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_revoke_all_removes_database_sessions_and_sanctum_tokens(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $user->createToken('test-device');

        DB::table('sessions')->insert([
            'id' => 'session-to-revoke',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PROXIWORK test',
            'payload' => 'test-payload',
            'last_activity' => now()->timestamp,
        ]);

        app(SessionRevocationService::class)->revokeAll($user);

        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
