<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(20);

        return view('client.notifications.index', compact('notifications'));
    }

    public function markAsRead(Request $request, string $notification)
    {
        $model = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $model->markAsRead();

        return back()->with('success', 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Toutes les notifications ont été marquées comme lues.');
    }
}