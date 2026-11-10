<?php

declare(strict_types=1);

namespace Tests\Feature\Notification;

use App\Models\NotificationTemplate;
use App\Models\User;
use App\Services\Notification\TransactionalNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_database_and_mail_templates_are_used_for_transactional_notifications(): void
    {
        app()->setLocale('en');
        $recipient = User::factory()->create(['name' => 'Aline']);

        NotificationTemplate::query()->create([
            'name' => 'Notification compte',
            'code' => 'account-db',
            'event_key' => 'account',
            'category' => 'account',
            'channel' => 'database',
            'locale' => 'fr',
            'title' => 'Bonjour {{ user_name }}',
            'body' => 'Consultez votre espace : {{ action_url }}',
            'status' => 'published',
            'version' => 1,
        ]);
        NotificationTemplate::query()->create([
            'name' => 'E-mail compte',
            'code' => 'account-mail',
            'event_key' => 'account',
            'category' => 'account',
            'channel' => 'mail',
            'locale' => 'fr',
            'subject' => 'Information pour {{ user_name }}',
            'title' => 'Information',
            'body' => 'Votre compte est prêt, {{ user_name }}.',
            'status' => 'published',
            'version' => 1,
        ]);

        NotificationTemplate::query()->create([
            'name' => 'SMS compte',
            'code' => 'account-sms',
            'event_key' => 'account',
            'category' => 'account',
            'channel' => 'sms',
            'locale' => 'fr',
            'title' => 'Information',
            'body' => 'Bonjour {{ user_name }}, votre compte est prêt.',
            'status' => 'published',
            'version' => 1,
        ]);
        NotificationTemplate::query()->create([
            'name' => 'Push compte',
            'code' => 'account-push',
            'event_key' => 'account',
            'category' => 'account',
            'channel' => 'push',
            'locale' => 'fr',
            'title' => 'Alerte pour {{ user_name }}',
            'body' => 'Ouvrez votre espace : {{ action_url }}',
            'status' => 'published',
            'version' => 1,
        ]);

        app(TransactionalNotificationService::class)->accountActivity(
            $recipient,
            'Titre de secours',
            'Message de secours',
            '/client',
            'template-render-test',
        );

        $notification = $recipient->notifications()->firstOrFail();
        $this->assertSame('Bonjour Aline', $notification->data['title']);
        $this->assertSame('Consultez votre espace : /client', $notification->data['message']);
        $this->assertSame('Information pour Aline', $notification->data['email_subject']);
        $this->assertSame('Votre compte est prêt, Aline.', $notification->data['email_message']);
        $this->assertSame('Bonjour Aline, votre compte est prêt.', $notification->data['sms_message']);
        $this->assertSame('Alerte pour Aline', $notification->data['push_title']);
        $this->assertSame('Ouvrez votre espace : /client', $notification->data['push_message']);
    }
}
