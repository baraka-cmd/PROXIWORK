<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailAddressChangedNotification;
use App\Notifications\PendingEmailChangeNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_email_change_keeps_current_address_until_new_address_is_confirmed(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'CurrentPassword123!',
        ]);
        $user->assignRole('client');

        $this->actingAs($user)
            ->post(route('account.email.update'), [
                'email' => 'new@example.com',
                'current_password' => 'CurrentPassword123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('old@example.com', $user->email);
        $this->assertSame('new@example.com', $user->pending_email);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentOnDemand(PendingEmailChangeNotification::class);
    }

    public function test_signed_email_change_confirmation_updates_email_and_logs_user_out(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => 'CurrentPassword123!',
        ]);
        $user->forceFill(['pending_email' => 'new@example.com'])->save();
        $user->assignRole('client');

        $url = URL::temporarySignedRoute(
            'web.email-change.confirm',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1('new@example.com')],
        );

        $this->actingAs($user)
            ->get($url)
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertGuest();

        Notification::assertSentOnDemand(EmailAddressChangedNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'auth.web.email_changed',
        ]);
    }

    public function test_email_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);
        $user->assignRole('client');

        $this->actingAs($user)
            ->from(route('account.email.edit'))
            ->post(route('account.email.update'), [
                'email' => 'new@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('account.email.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->pending_email);
        $this->assertSame('old@example.com', $user->fresh()->email);
    }
}
