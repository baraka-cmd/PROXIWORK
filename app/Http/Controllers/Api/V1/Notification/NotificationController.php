<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Http\Resources\Notification\NotificationPreferenceResource;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\NotificationPreference;
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

    public function index(Request $request)
    {
        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'string', Rule::in(['all', 'unread', 'read'])],
            'category' => ['sometimes', 'string', Rule::in(array_merge(['all'], self::CATEGORIES))],
            'q' => ['sometimes', 'string', 'max:100'],
            'archived' => ['sometimes', 'boolean'],
        ]);

        $query = $request->user()->notifications()->latest();
        $archived = in_array(($filters['archived'] ?? false), [true, 1, '1', 'true'], true);
        $archived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');

        if (($filters['status'] ?? 'all') === 'unread') {
            $query->whereNull('read_at');
        } elseif (($filters['status'] ?? 'all') === 'read') {
            $query->whereNotNull('read_at');
        }

        $category = $filters['category'] ?? 'all';
        if ($category === 'account') {
            $query->whereIn('data->action', ['account', 'profile_updated', 'password_changed', 'password_reset', 'email_changed']);
        } elseif ($category !== 'all') {
            $query->where('data->action', $category);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $term = '%'.$search.'%';
                $builder->where('data->title', 'like', $term)
                    ->orWhere('data->message', 'like', $term);
            });
        }

        $notifications = $query->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();

        return NotificationResource::collection($notifications)->additional([
            'message' => 'Notifications récupérées avec succès.',
            'meta' => [],
        ]);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $model = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        if ($model->read_at === null) {
            $model->markAsRead();
        }

        return (new NotificationResource($model->fresh()))->additional([
            'message' => 'Notification marquée comme lue.',
            'meta' => [],
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->whereNull('archived_at')->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Les notifications actives ont été marquées comme lues.',
            'data' => null,
            'meta' => [],
        ]);
    }

    public function archive(Request $request, string $notification)
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->whereNull('archived_at')
            ->firstOrFail();

        $model->forceFill([
            'archived_at' => now(),
            'read_at' => $model->read_at ?? now(),
        ])->save();

        return (new NotificationResource($model->fresh()))->additional([
            'message' => 'Notification archivée sans suppression de son historique.',
            'meta' => [],
        ]);
    }

    public function restore(Request $request, string $notification)
    {
        $model = $request->user()->notifications()
            ->whereKey($notification)
            ->whereNotNull('archived_at')
            ->firstOrFail();

        $model->forceFill(['archived_at' => null])->save();

        return (new NotificationResource($model->fresh()))->additional([
            'message' => 'Notification restaurée.',
            'meta' => [],
        ]);
    }

    public function preferences(Request $request): NotificationPreferenceResource
    {
        $preference = $request->user()->notificationPreference()->first()
            ?? new NotificationPreference([
                'database_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => false,
                'push_enabled' => false,
            ]);

        return (new NotificationPreferenceResource($preference))->additional([
            'message' => 'Préférences de notification récupérées avec succès.',
            'meta' => [],
        ]);
    }

    public function updatePreferences(UpdateNotificationPreferenceRequest $request): NotificationPreferenceResource
    {
        $preference = $request->user()->notificationPreference()->firstOrCreate([], [
            'database_enabled' => true,
            'email_enabled' => true,
            'sms_enabled' => false,
            'push_enabled' => false,
        ]);
        $preference->update($request->validated());

        return (new NotificationPreferenceResource($preference->refresh()))->additional([
            'message' => 'Préférences de notification mises à jour avec succès.',
            'meta' => [],
        ]);
    }
}
