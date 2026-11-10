<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationDeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:PENDING,RETRYING,PROCESSING,SENT,DELIVERED,FAILED,UNKNOWN'],
            'channel' => ['nullable', 'string', 'in:mail,sms,push'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:15,30,50'],
        ]);

        $query = NotificationDelivery::query()
            ->with(['user:id,name', 'notification'])
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['channel'] ?? null, fn ($builder, $channel) => $builder->where('channel', $channel))
            ->when($filters['search'] ?? null, function ($builder, $search): void {
                $builder->where(function ($nested) use ($search): void {
                    if (ctype_digit($search)) {
                        $nested->whereKey((int) $search)
                            ->orWhere('user_id', (int) $search);
                    }

                    $nested->orWhere('recipient_hint', 'like', '%'.$search.'%')
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', '%'.$search.'%'));
                });
            });

        $deliveries = $query->latest('id')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString();

        $base = NotificationDelivery::query();

        return view('admin.notifications.deliveries.index', [
            'deliveries' => $deliveries,
            'filters' => $filters,
            'summary' => [
                'total' => (clone $base)->count(),
                'pending' => (clone $base)->whereIn('status', ['PENDING', 'RETRYING'])->count(),
                'processing' => (clone $base)->where('status', 'PROCESSING')->count(),
                'sent' => (clone $base)->whereIn('status', ['SENT', 'DELIVERED'])->count(),
                'attention' => (clone $base)->whereIn('status', ['FAILED', 'UNKNOWN'])->count(),
            ],
        ]);
    }

    public function show(NotificationDelivery $delivery): View
    {
        $delivery->load(['user:id,name', 'notification']);

        return view('admin.notifications.deliveries.show', [
            'delivery' => $delivery,
        ]);
    }
}
