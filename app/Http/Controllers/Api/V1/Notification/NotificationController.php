<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Http\Requests\Notification\UpdateNotificationPreferenceRequest;
use App\Http\Resources\Notification\NotificationPreferenceResource;
use App\Http\Resources\Notification\NotificationResource;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return NotificationResource::collection($notifications)->additional([
            'message' => 'Notifications récupérées avec succès.',
            'meta' => [],
        ]);
    }

    public function markAsRead(Request $request, string $notification)
    {
        $model = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return (new NotificationResource($model->fresh()))->additional([
            'message' => 'Notification marquée comme lue.',
            'meta' => [],
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
            'data' => null,
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
        $preference = $request->user()->notificationPreference()->firstOrCreate();
        $preference->update($request->validated());

        return (new NotificationPreferenceResource($preference->refresh()))->additional([
            'message' => 'Préférences de notification mises à jour avec succès.',
            'meta' => [],
        ]);
    }
}
