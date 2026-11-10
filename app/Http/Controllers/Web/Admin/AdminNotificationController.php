<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:read,unread'],
            'action' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'in:15,30,50'],
        ]);

        $query = $this->baseQuery()
            ->when(($filters['status'] ?? null) === 'read', fn (Builder $builder) => $builder->whereNotNull('notifications.read_at'))
            ->when(($filters['status'] ?? null) === 'unread', fn (Builder $builder) => $builder->whereNull('notifications.read_at'))
            ->when($filters['action'] ?? null, fn (Builder $builder, string $action) => $builder->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(notifications.data, '$.action')) = ?",
                [$action],
            ))
            ->when($filters['search'] ?? null, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested->where('users.name', 'like', '%'.$search.'%')
                        ->orWhere('notifications.id', 'like', '%'.$search.'%');
                    if (ctype_digit($search)) {
                        $nested->orWhere('users.id', (int) $search);
                    }
                });
            });

        $notifications = $query
            ->select([
                'notifications.id',
                'notifications.data',
                'notifications.read_at',
                'notifications.created_at',
                'users.id as user_id',
                'users.name as recipient_name',
            ])
            ->latest('notifications.created_at')
            ->paginate((int) ($filters['per_page'] ?? 15))
            ->withQueryString()
            ->through(function (object $row): object {
                $row->data = json_decode((string) $row->data, true) ?: [];

                return $row;
            });

        $base = $this->baseQuery();

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'filters' => $filters,
            'summary' => [
                'total' => (clone $base)->count(),
                'unread' => (clone $base)->whereNull('notifications.read_at')->count(),
                'read' => (clone $base)->whereNotNull('notifications.read_at')->count(),
            ],
        ]);
    }

    public function show(string $notification): View
    {
        $row = $this->baseQuery()
            ->select([
                'notifications.id',
                'notifications.data',
                'notifications.read_at',
                'notifications.created_at',
                'notifications.updated_at',
                'users.id as user_id',
                'users.name as recipient_name',
            ])
            ->where('notifications.id', $notification)
            ->firstOrFail();
        $row->data = json_decode((string) $row->data, true) ?: [];

        return view('admin.notifications.show', ['notification' => $row]);
    }

    private function baseQuery(): Builder
    {
        return DB::table('notifications')
            ->join('users', 'users.id', '=', 'notifications.notifiable_id')
            ->where('notifications.notifiable_type', (new User())->getMorphClass());
    }
}
