<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_user_can_change_password_and_all_access_tokens_are_revoked(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'password' => 'CurrentPassword123!',
        ]);
        $user->assignRole('client');
        $user->createToken('another-device');

        $this->actingAs($user)
            ->withSession(['auth.session_version' => 0])
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('NewPassword456!', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.web.password_changed',
        ]);
        Notification::assertSentTo($user, AccountActivityNotification::class);
        $this->assertGuest();
    }

    public function test_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'CurrentPassword123!',
        ]);
        $user->assignRole('client');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'WrongPassword123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword123!', $user->fresh()->password));
        $this->assertSame(0, $user->fresh()->session_version);
    }
}
