<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()->notifications()->latest();

        if ($request->filled('status')) {
            if ($request->string('status')->value() === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->string('status')->value() === 'read') {
                $query->whereNotNull('read_at');
            }
        }

        $notifications = $query->paginate(20)->withQueryString();

        return view('professional.notifications.index', [
            'notifications' => $notifications,
            'status' => (string) $request->input('status', ''),
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $model = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $model->markAsRead();

        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}
