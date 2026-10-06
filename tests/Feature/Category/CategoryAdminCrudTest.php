<?php

declare(strict_types=1);

namespace Tests\Feature\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_client_cannot_manage_categories(): void
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/admin/categories', ['name' => 'Design'])
            ->assertForbidden();
    }

    public function test_admin_can_create_category_with_generated_slug(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Développement Web',
                'description' => 'Services web.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'developpement-web')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_admin_can_search_filter_and_paginate_categories(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        Category::create(['name' => 'Design', 'slug' => 'design']);
        Category::create(['name' => 'Plomberie', 'slug' => 'plomberie', 'status' => CategoryStatus::INACTIVE]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/categories?search=design&status=active&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'design');
    }

    public function test_admin_can_update_category_without_changing_slug_implicitly(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'Développement Web', 'slug' => 'developpement-web']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/categories/'.$category->id, ['name' => 'Développement Web & Mobile'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'developpement-web');
    }

    public function test_admin_can_create_child_but_parent_must_be_active(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $parent = Category::create(['name' => 'Développement', 'slug' => 'developpement']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'parent_id' => $parent->id,
                'name' => 'Web',
            ])
            ->assertCreated();

        $parent->update(['status' => CategoryStatus::INACTIVE]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', [
                'parent_id' => $parent->id,
                'name' => 'Mobile',
            ])
            ->assertStatus(422);
    }

    public function test_admin_cannot_create_hierarchy_cycle(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $a = Category::create(['name' => 'A', 'slug' => 'a']);
        $b = Category::create(['parent_id' => $a->id, 'name' => 'B', 'slug' => 'b']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/categories/'.$a->id, ['parent_id' => $b->id])
            ->assertStatus(422);
    }

    public function test_admin_cannot_create_third_hierarchy_level(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $root = Category::create(['name' => 'Services', 'slug' => 'services']);
        $child = Category::create(['parent_id' => $root->id, 'name' => 'Conseil', 'slug' => 'conseil']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', ['parent_id' => $child->id, 'name' => 'Audit'])
            ->assertStatus(422);
    }

    public function test_admin_cannot_move_root_with_children_under_another_category(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $root = Category::create(['name' => 'A', 'slug' => 'a']);
        Category::create(['parent_id' => $root->id, 'name' => 'A Child', 'slug' => 'a-child']);
        $target = Category::create(['name' => 'B', 'slug' => 'b']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/categories/'.$root->id, ['parent_id' => $target->id])
            ->assertStatus(422);
    }

    public function test_admin_cannot_archive_category_with_active_children(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $parent = Category::create(['name' => 'Développement', 'slug' => 'developpement']);
        Category::create(['parent_id' => $parent->id, 'name' => 'Web', 'slug' => 'web']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/admin/categories/'.$parent->id)
            ->assertStatus(422);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
            'status' => CategoryStatus::ACTIVE->value,
        ]);
    }

    public function test_delete_endpoint_archives_category_instead_of_physically_deleting_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'Photographie', 'slug' => 'photographie']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/v1/admin/categories/'.$category->id)
            ->assertNoContent();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'status' => CategoryStatus::ARCHIVED->value,
        ]);
    }

    public function test_admin_can_set_featured_and_sort_order(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = Category::create(['name' => 'Design', 'slug' => 'design']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/categories/'.$category->id, [
                'is_featured' => true,
                'sort_order' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_featured', true)
            ->assertJsonPath('data.sort_order', 10);
    }

    public function test_category_management_is_audited(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/categories', ['name' => 'Design'])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => 'category_created', 'user_id' => $admin->id]);
    }
}
