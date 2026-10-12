<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    private const CATEGORIES = [
        'service_request',
        'quotation',
        'order',
        'payment',
        'message',
        'review',
        'account',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['all', 'unread', 'read'])],
            'category' => ['nullable', 'string', Rule::in(array_merge(['all'], self::CATEGORIES))],
            'q' => ['nullable', 'string', 'max:100'],
            'archived' => ['nullable', 'string', Rule::in(['0', '1'])],
        ]);

        $user = $request->user();
        $status = $filters['status'] ?? 'all';
        $category = $filters['category'] ?? 'all';
        $search = trim((string) ($filters['q'] ?? ''));
        $archived = ($filters['archived'] ?? '0') === '1';

        $query = $user->notifications()->latest();

        $archived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($category !== 'all') {
            $query->where('data->action', $category);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('data->title', 'like', $term)
                    ->orWhere('data->message', 'like', $term);
            });
        }

        $notifications = $query->paginate(15)->withQueryString();

        $notifications->getCollection()->each(function ($notification): void {
            $notification->safe_url = $this->safeInternalUrl(
                is_string($notification->data['url'] ?? null)
                    ? $notification->data['url']
                    : null,
            );
        });

        $unreadCount = $user->unreadNotifications()->whereNull('archived_at')->count();

        $preferences = $user->notificationPreference()->first()
            ?? new NotificationPreference([
                'database_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
            ]);

        return view('client.notifications.index', compact(
            'notifications',
            'unreadCount',
            'status',
            'category',
            'search',
            'archived',
            'preferences',
        ));
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->whereNull('archived_at')
            ->firstOrFail();

        if ($model->read_at === null) {
            $model->markAsRead();
        }

        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->whereNull('archived_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Les notifications actives ont été marquées comme lues.');
    }

    public function archive(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->whereNull('archived_at')
            ->firstOrFail();

        $model->forceFill([
            'archived_at' => now(),
            'read_at' => $model->read_at ?? now(),
        ])->save();

        return back()->with('success', 'Notification archivée. Elle reste conservée dans votre historique.');
    }

    public function updatePreferences(UpdateNotificationPreferenceRequest $request): RedirectResponse
    {
        $preference = $request->user()->notificationPreference()->firstOrCreate([], [
            'database_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,
        ]);

        $preference->update($request->validated());

        return back()->with('success', 'Vos préférences de notification ont été enregistrées.');
    }

    public function restore(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->whereNotNull('archived_at')
            ->firstOrFail();

        $model->forceFill(['archived_at' => null])->save();

        return back()->with('success', 'Notification restaurée dans votre boîte de réception.');
    }

    private function safeInternalUrl(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return $url;
        }

        $target = parse_url($url);
        $application = parse_url((string) config('app.url'));

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
