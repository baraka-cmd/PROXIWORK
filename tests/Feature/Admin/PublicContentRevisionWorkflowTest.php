<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CmsPage;
use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentRevisionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_cms_page_stays_live_until_its_draft_revision_is_published(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $page = CmsPage::query()->create([
            'title' => 'À propos',
            'slug' => 'a-propos',
            'body' => 'Version publique initiale',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);
        $page->versions()->create([
            'version' => 1,
            'content' => [
                'title' => 'À propos',
                'slug' => 'a-propos',
                'body' => 'Version publique initiale',
                'is_indexable' => true,
            ],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.communication.pages.update', $page), [
            'title' => 'À propos de PROXIWORK',
            'slug' => 'a-propos',
            'body' => 'Nouvelle version en préparation',
            'is_indexable' => true,
        ])->assertRedirect();

        $this->assertSame('Version publique initiale', $page->fresh()->body);
        $this->assertDatabaseHas('cms_page_versions', [
            'cms_page_id' => $page->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->post(route('admin.communication.pages.publish', $page))->assertRedirect();

        $this->assertSame('Nouvelle version en préparation', $page->fresh()->body);
    }

    public function test_published_faq_answer_stays_live_until_its_draft_revision_is_published(): void
    {
        $this->seed(RbacSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $category = FaqCategory::query()->create(['name' => 'Compte', 'slug' => 'compte']);

        $article = FaqArticle::query()->create([
            'faq_category_id' => $category->id,
            'question' => 'Question initiale ?',
            'slug' => 'question-initiale',
            'answer' => 'Réponse initiale',
            'status' => 'published',
            'published_at' => now(),
            'audience' => 'all',
            'locale' => 'fr',
        ]);
        $article->versions()->create([
            'version' => 1,
            'content' => [
                'faq_category_id' => $category->id,
                'question' => 'Question initiale ?',
                'slug' => 'question-initiale',
                'answer' => 'Réponse initiale',
                'audience' => 'all',
                'locale' => 'fr',
                'sort_order' => 0,
            ],
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.communication.faq.update', $article), [
            'faq_category_id' => $category->id,
            'question' => 'Question révisée ?',
            'answer' => 'Réponse révisée',
            'audience' => 'all',
            'locale' => 'fr',
            'sort_order' => 0,
        ])->assertRedirect();

        $this->assertSame('Réponse initiale', $article->fresh()->answer);
        $this->assertDatabaseHas('faq_article_versions', [
            'faq_article_id' => $article->id,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->post(route('admin.communication.faq.publish', $article))->assertRedirect();

        $this->assertSame('Réponse révisée', $article->fresh()->answer);
    }
}
