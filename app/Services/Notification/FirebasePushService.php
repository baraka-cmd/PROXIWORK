<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebasePushService
{
    public function send(string $deviceToken, array $message): Response
    {
        $projectId = trim((string) config('services.firebase.project_id'));
        if ($projectId === '') {
            throw new RuntimeException('Firebase project is not configured.');
        }

        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(12)
            ->post('https://fcm.googleapis.com/v1/projects/'.$projectId.'/messages:send', [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => (string) ($message['title'] ?? 'PROXIWORK'),
                        'body' => (string) ($message['body'] ?? 'Vous avez une nouvelle notification.'),
                    ],
                    'data' => collect($message['data'] ?? [])
                        ->map(fn ($value): string => is_scalar($value) ? (string) $value : '')
                        ->all(),
                    'android' => ['priority' => 'HIGH'],
                    'apns' => ['headers' => ['apns-priority' => '10']],
                ],
            ]);
    }

    private function accessToken(): string
    {
        $projectId = trim((string) config('services.firebase.project_id'));

        return Cache::remember('proxiwork.firebase.access_token.'.$projectId, now()->addMinutes(55), function (): string {
            $clientEmail = trim((string) config('services.firebase.client_email'));
            $privateKey = str_replace('\\n', "\n", (string) config('services.firebase.private_key'));

            if ($clientEmail === '' || $privateKey === '') {
                throw new RuntimeException('Firebase service-account credentials are not configured.');
            }

            $issuedAt = time();
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $issuedAt,
                'exp' => $issuedAt + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsigned = $header.'.'.$claims;

            $signature = '';
            $privateKeyResource = openssl_pkey_get_private($privateKey);
            if ($privateKeyResource === false || ! openssl_sign($unsigned, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Firebase private key could not sign the service token.');
            }

            $jwt = $unsigned.'.'.$this->base64UrlEncode($signature);
            $response = Http::asForm()->acceptJson()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $accessToken = $response->json('access_token');
            if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Firebase OAuth token exchange failed with HTTP '.$response->status().'.');
            }

            return $accessToken;
        });
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
