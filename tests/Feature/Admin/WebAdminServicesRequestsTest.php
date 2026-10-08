<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceStatus;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Skill;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAdminServicesRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    public function test_admin_can_browse_services_and_filter_by_status(): void
    {
        $admin = $this->admin();
        $published = Service::factory()->create(['status' => ServiceStatus::PUBLISHED, 'published_at' => now()]);
        Service::factory()->create(['status' => ServiceStatus::DRAFT]);

        $this->actingAs($admin)->get(route('admin.services.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee($published->title);
    }

    public function test_user_without_service_permission_cannot_browse_admin_services(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('admin.services.index'))->assertForbidden();
    }

    public function test_admin_can_publish_a_service_through_domain_service(): void
    {
        $admin = $this->admin();
        $service = Service::factory()->create();
        $service->skills()->attach(Skill::factory()->create());
        $service->images()->create([
            'path' => 'services/cover.webp',
            'alt_text' => 'Cover',
            'sort_order' => 0,
            'is_cover' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.services.publish', $service))
            ->assertRedirect();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'status' => ServiceStatus::PUBLISHED->value,
        ]);
    }

    public function test_admin_can_browse_requests_and_isolated_detail(): void
    {
        $admin = $this->admin();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::REQUESTED]);

        $this->actingAs($admin)->get(route('admin.service-requests.index', ['status' => 'requested']))
            ->assertOk()
            ->assertSee($request->title);

        $this->actingAs($admin)->get(route('admin.service-requests.show', $request))
            ->assertOk()
            ->assertSee($request->title);
    }

    public function test_admin_can_cancel_requested_request_and_history_is_created(): void
    {
        $admin = $this->admin();
        $request = ServiceRequest::factory()->create(['status' => ServiceRequestStatus::REQUESTED]);

        $this->actingAs($admin)->post(route('admin.service-requests.cancel', $request), [
            'reason' => 'Décision administrative',
        ])->assertRedirect();

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => ServiceRequestStatus::CANCELLED->value,
        ]);
        $this->assertDatabaseHas('service_request_status_histories', [
            'service_request_id' => $request->id,
            'from_status' => ServiceRequestStatus::REQUESTED->value,
            'to_status' => ServiceRequestStatus::CANCELLED->value,
            'changed_by' => $admin->id,
        ]);
    }
}
