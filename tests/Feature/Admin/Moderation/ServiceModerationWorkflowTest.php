<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Moderation;

use App\Enums\ServiceModerationStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceModerationDecision;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_admin_can_hide_service_and_the_decision_is_audited_and_notified(): void
    {
        Notification::fake();

        $service = Service::factory()->create([
            'status' => ServiceStatus::PUBLISHED,
            'published_at' => now(),
        ]);
        $owner = $service->professionalProfile->user;
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->from(route('admin.services.show', $service))
            ->post(route('admin.services.moderate', $service), [
                'decision' => 'hide',
                'reason' => 'La description doit être corrigée avant une nouvelle publication.',
            ])
            ->assertRedirect(route('admin.services.show', $service));

        $service->refresh();

        $this->assertSame(ServiceModerationStatus::HIDDEN, $service->moderation_status);
        $this->assertSame(ServiceStatus::PUBLISHED, $service->status);
        $this->assertFalse(Service::query()->requestable()->whereKey($service->getKey())->exists());

        $this->assertDatabaseHas('service_moderation_decisions', [
            'service_id' => $service->getKey(),
            'actor_id' => $admin->getKey(),
            'action' => 'hide',
            'from_status' => ServiceModerationStatus::CLEAR->value,
            'to_status' => ServiceModerationStatus::HIDDEN->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->getKey(),
            'action' => 'service_moderation_hide',
        ]);

        Notification::assertSentTo($owner, AccountActivityNotification::class);
    }

    public function test_professional_can_resubmit_a_hidden_service_for_review(): void
    {
        $service = Service::factory()->create([
            'moderation_status' => ServiceModerationStatus::HIDDEN,
            'moderation_reason' => 'La présentation commerciale doit être précisée.',
        ]);
        $owner = $service->professionalProfile->user;
        $owner->assignRole('professional');

        $this->actingAs($owner)
            ->post(route('professional.services.submit-review', $service), [
                'note' => 'La description et les conditions ont été corrigées.',
            ])
            ->assertRedirect(route('professional.services.index'));

        $this->assertSame(
            ServiceModerationStatus::UNDER_REVIEW,
            $service->fresh()->moderation_status,
        );

        $this->assertDatabaseHas('service_moderation_decisions', [
            'service_id' => $service->getKey(),
            'actor_id' => $owner->getKey(),
            'action' => 'submitted_for_review',
            'from_status' => ServiceModerationStatus::HIDDEN->value,
            'to_status' => ServiceModerationStatus::UNDER_REVIEW->value,
        ]);
    }


    public function test_professional_cannot_republish_a_service_hidden_by_administration(): void
    {
        $service = Service::factory()->create([
            'moderation_status' => ServiceModerationStatus::HIDDEN,
            'moderation_reason' => 'Le contenu doit être corrigé avant sa remise en visibilité.',
        ]);
        $owner = $service->professionalProfile->user;
        $owner->assignRole('professional');
        $service->images()->create([
            'path' => 'services/'.$service->getKey().'/cover.webp',
            'is_cover' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/professional/services/'.$service->getKey().'/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['moderation']);

        $this->assertSame(ServiceModerationStatus::HIDDEN, $service->fresh()->moderation_status);
        $this->assertSame(ServiceStatus::DRAFT, $service->fresh()->status);
    }

    public function test_moderator_cannot_apply_an_invalid_transition(): void
    {
        $service = Service::factory()->create();
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $this->actingAs($moderator)
            ->post(route('admin.services.moderate', $service), [
                'decision' => 'reject',
                'reason' => 'Le contenu ne respecte pas les règles de la plateforme.',
            ])
            ->assertSessionHasErrors('decision');

        $this->assertSame(ServiceModerationStatus::CLEAR, $service->fresh()->moderation_status);
        $this->assertSame(0, ServiceModerationDecision::query()->where('service_id', $service->getKey())->count());
    }
}
