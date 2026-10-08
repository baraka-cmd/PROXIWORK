<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function moderator(): User
    {
        $this->seed(RbacSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(Role::query()->where('name', 'moderator')->firstOrFail());

        return $user;
    }

    public function test_moderator_can_open_report_center_and_report_detail(): void
    {
        $moderator = $this->moderator();
        $reporter = User::factory()->create();
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => User::class,
            'target_id' => $reporter->id,
            'reason_code' => 'ABUSE',
            'description' => 'Test signalement',
        ]);

        $this->actingAs($moderator)
            ->get(route('admin.reports.index'))
            ->assertOk();

        $this->actingAs($moderator)
            ->get(route('admin.reports.show', $report))
            ->assertOk();
    }

    public function test_report_must_be_started_before_resolution(): void
    {
        $moderator = $this->moderator();
        $reporter = User::factory()->create();
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => User::class,
            'target_id' => $reporter->id,
            'reason_code' => 'ABUSE',
        ]);

        $this->actingAs($moderator)
            ->post(route('admin.reports.resolve', $report), [
                'resolution_note' => 'Tentative invalide',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(ReportStatus::PENDING, $report->refresh()->status);
    }

    public function test_report_moderation_cannot_escalate_beyond_actor_permissions(): void
    {
        $moderator = $this->moderator();
        $reporter = User::factory()->create();
        $report = Report::query()->create([
            'reporter_id' => $reporter->id,
            'target_type' => User::class,
            'target_id' => $reporter->id,
            'reason_code' => 'ABUSE',
        ]);

        $this->actingAs($moderator)
            ->post(route('admin.reports.start-review', $report))
            ->assertRedirect();

        $this->actingAs($moderator)
            ->post(route('admin.reports.moderate', $report), [
                'action_type' => 'suspend_user',
            ])
            ->assertSessionHasErrors('action_type');

        $this->assertSame(ReportStatus::UNDER_REVIEW, $report->refresh()->status);
        $this->assertSame('active', $reporter->refresh()->account_status->value);
    }

    public function test_non_moderator_cannot_access_reports(): void
    {
        $user = User::factory()->create();
        $this->seed(RbacSeeder::class);

        $this->actingAs($user)
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }
}
