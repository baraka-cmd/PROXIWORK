<?php

declare(strict_types=1);

namespace Tests\Feature\PublicContent;

use App\Models\CmsPage;
use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Models\InstitutionalDocument;
use App\Models\InstitutionalDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_cms_page_is_server_rendered_and_escaped(): void
    {
        $page = CmsPage::query()->create([
            'title' => 'Notre mission',
            'slug' => 'notre-mission',
            'body' => '<script>alert(1)</script>',
            'status' => 'published',
            'published_at' => now(),
            'is_indexable' => true,
        ]);

        $this->get(route('public.pages.show', $page->slug))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_faq_feedback_is_counted_once_per_visitor_fingerprint(): void
    {
        $category = FaqCategory::query()->create(['name' => 'Compte', 'slug' => 'compte']);
        $article = FaqArticle::query()->create([
            'faq_category_id' => $category->id,
            'question' => 'Comment créer un compte ?',
            'slug' => 'creer-un-compte',
            'answer' => 'Remplissez le formulaire.',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('public.faq.feedback', $article), ['helpful' => '1'])
            ->assertRedirect();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->post(route('public.faq.feedback', $article), ['helpful' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('faq_articles', ['id' => $article->id, 'helpful_yes' => 1]);
    }

    public function test_acceptance_is_bound_to_the_exact_published_document_version(): void
    {
        $user = User::factory()->create();
        $document = InstitutionalDocument::query()->create([
            'title' => 'Conditions générales',
            'slug' => 'conditions-generales',
            'document_type' => 'terms',
            'status' => 'published',
            'current_version' => 1,
            'requires_acceptance' => true,
        ]);
        $version = InstitutionalDocumentVersion::query()->create([
            'institutional_document_id' => $document->id,
            'version' => 1,
            'content' => 'Conditions applicables.',
            'status' => 'published',
            'effective_at' => now()->subMinute(),
            'published_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('public.legal.accept', $document->slug))
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('public.legal.accept', $document->slug))
            ->assertRedirect();

        $this->assertDatabaseCount('institutional_document_acceptances', 1);
        $this->assertDatabaseHas('institutional_document_acceptances', [
            'institutional_document_version_id' => $version->id,
            'user_id' => $user->id,
        ]);
    }
}
