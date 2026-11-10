<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Communication;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\NotificationTemplate;
use App\Models\PublicPage;
use App\Models\User;
use App\Services\Notification\NotificationTemplateRenderer;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationContentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_notification_templates_support_french_and_english_variants_for_the_same_event(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $response = $this->actingAs($this->admin)->post(route('admin.communication.templates.store'), [
                'key' => 'order',
                'name' => 'Order notification',
                'channel' => 'database',
                'locale' => $locale,
                'title' => $locale === 'fr' ? 'Bonjour {{ prenom }}' : 'Hello {{ prenom }}',
                'body' => 'Information PROXIWORK',
                'variables' => ['prenom'],
            ]);
            $response->assertRedirect(route('admin.communication.templates'));
        }

        $this->assertDatabaseCount('notification_templates', 2);
    }

    public function test_template_renderer_uses_the_recipient_profile_locale(): void
    {
        $recipient = User::factory()->create();
        $recipient->profile()->create(['locale' => 'en', 'first_name' => 'Alex']);
        NotificationTemplate::create([
            'key' => 'message',
            'name' => 'Message EN',
            'channel' => 'database',
            'locale' => 'en',
            'title' => 'Hello {{ prenom }}',
            'body' => 'You have a message.',
            'variables' => ['prenom'],
            'status' => 'active',
            'version' => 1,
        ]);

        $rendered = app(NotificationTemplateRenderer::class)->render('message', $recipient, 'Fallback', 'Fallback body');

        $this->assertSame('Hello Alex', $rendered['title']);
        $this->assertSame(1, $rendered['version']);
    }

    public function test_notification_template_rejects_undeclared_or_unknown_variables(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.communication.templates.store'), [
            'key' => 'service_request',
            'name' => 'Demande créée',
            'channel' => 'database',
            'locale' => 'fr',
            'title' => 'Bonjour {{ prenom }}',
            'body' => 'Une nouvelle demande {{ montant }}',
            'variables' => ['prenom'],
        ]);

        $response->assertSessionHasErrors('body');
        $this->assertDatabaseMissing('notification_templates', ['key' => 'service_request']);
    }

    public function test_public_pages_are_hidden_until_published_and_render_text_safely(): void
    {
        $page = PublicPage::create([
            'title' => 'À propos',
            'slug' => 'a-propos',
            'body' => '<script>alert(1)</script>',
            'status' => 'draft',
        ]);

        $this->get('/pages/a-propos')->assertNotFound();

        $page->update(['status' => 'published', 'published_at' => now()]);
        $response = $this->get('/pages/a-propos');

        $response->assertOk();
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_faq_filters_items_by_audience(): void
    {
        $category = FaqCategory::create(['name' => 'Professionnels', 'slug' => 'professionnels', 'is_active' => true]);
        FaqItem::create([
            'faq_category_id' => $category->id,
            'question' => 'Question client',
            'answer' => 'Réponse client',
            'audience' => 'client',
            'is_active' => true,
        ]);
        FaqItem::create([
            'faq_category_id' => $category->id,
            'question' => 'Question professionnel',
            'answer' => 'Réponse professionnel',
            'audience' => 'professional',
            'is_active' => true,
        ]);

        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get('/faq')
            ->assertOk()
            ->assertSee('Question client')
            ->assertDontSee('Question professionnel');
    }

    public function test_admin_can_create_a_campaign_for_a_server_defined_audience(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.communication.campaigns.store'), [
            'title' => 'Information professionnels',
            'body' => 'Une information importante.',
            'role' => 'professional',
            'channels' => ['mail'],
            'priority' => 'normal',
            'confirm_broadcast' => '1',
        ]);

        $response->assertRedirect(route('admin.communication.campaigns'));
        $this->assertDatabaseHas('communication_campaigns', [
            'title' => 'Information professionnels',
            'status' => 'draft',
        ]);
    }

    public function test_users_without_communication_permissions_cannot_open_admin_module(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get(route('admin.communication.notifications'))->assertForbidden();
    }
}
