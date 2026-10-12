<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;

class TransactionalNotificationMail extends Mailable
{
    public function __construct(
        private readonly string $notificationTitle,
        private readonly string $notificationMessage,
        private readonly ?string $notificationUrl = null,
    ) {}

    public function build(): static
    {
        return $this
            ->subject($this->notificationTitle)
            ->view('emails.notifications.transactional')
            ->with([
                'title' => $this->notificationTitle,
                'message' => $this->notificationMessage,
                'url' => $this->safeInternalUrl($this->notificationUrl),
                'applicationName' => config('app.name', 'PROXIWORK'),
            ]);
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);
        $application = parse_url((string) config('app.url'));

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return url($url);
        }

        $target = parse_url($url);

        if (
            ! is_array($target)
            || ! is_array($application)
            || empty($target['host'])
            || empty($application['host'])
            || ! in_array(strtolower((string) ($target['scheme'] ?? 'https')), ['http', 'https'], true)
            || strtolower((string) $target['host']) !== strtolower((string) $application['host'])
        ) {
            return null;
        }

        return $url;
    }
}
