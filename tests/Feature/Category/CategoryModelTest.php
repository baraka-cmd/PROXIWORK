<?php

declare(strict_types=1);

namespace Tests\Feature\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_can_be_created_as_a_root_category(): void
    {
        $category = Category::create([
            'name' => 'Informatique & Technologie',
            'slug' => 'informatique-technologie',
        ]);

        $this->assertNull($category->parent_id);
        $this->assertSame(CategoryStatus::ACTIVE, $category->status);
        $this->assertFalse($category->is_featured);
        $this->assertSame(0, $category->sort_order);
    }

    public function test_category_supports_parent_and_children_relationships(): void
    {
        $parent = Category::create([
            'name' => 'Informatique & Technologie',
            'slug' => 'informatique-technologie',
        ]);

        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Développement Web',
            'slug' => 'developpement-web',
        ]);

        $this->assertTrue($child->parent->is($parent));
        $this->assertTrue($parent->children->contains(fn (Category $category): bool => $category->is($child)));
    }

    public function test_status_is_cast_to_category_status_enum(): void
    {
        $category = Category::create([
            'name' => 'Construction',
            'slug' => 'construction',
            'status' => CategoryStatus::INACTIVE,
        ]);

        $this->assertSame(CategoryStatus::INACTIVE, $category->fresh()->status);
    }

    public function test_category_can_be_filtered_with_active_and_root_scopes(): void
    {
        $activeRoot = Category::create([
            'name' => 'Design',
            'slug' => 'design',
        ]);
        Category::create([
            'name' => 'Education',
            'slug' => 'education',
            'status' => CategoryStatus::INACTIVE,
        ]);
        Category::create([
            'parent_id' => $activeRoot->id,
            'name' => 'UI/UX Design',
            'slug' => 'ui-ux-design',
        ]);

        $this->assertSame([$activeRoot->id], Category::active()->root()->pluck('id')->all());
    }

    public function test_slug_is_globally_unique(): void
    {
        Category::create([
            'name' => 'Développement Web',
            'slug' => 'developpement-web',
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        Category::create([
            'name' => 'Web Development',
            'slug' => 'developpement-web',
        ]);
    }

    public function test_parent_can_be_deleted_without_deleting_child(): void
    {
        $parent = Category::create([
            'name' => 'Services',
            'slug' => 'services',
        ]);
        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Conseil',
            'slug' => 'conseil',
        ]);

        $parent->delete();

        $this->assertDatabaseHas('categories', ['id' => $child->id, 'parent_id' => null]);
    }

    public function test_server_controlled_fields_are_not_mass_assignable(): void
    {
        $category = Category::create([
            'name' => 'Photographie',
            'slug' => 'photographie',
            'id' => 9999,
        ]);

        $this->assertNotSame(9999, $category->id);
    }
}
