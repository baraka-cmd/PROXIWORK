<?php

declare(strict_types=1);

namespace Tests\Feature\Category;

use App\Models\Category;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_active_categories_are_public(): void
    {
        Category::create(['name' => 'Design', 'slug' => 'design']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'design');
    }

    public function test_missing_category_is_not_found(): void
    {
        $this->getJson('/api/v1/categories/999999')->assertNotFound();
    }
}
