<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Permission::class);

        $query = Permission::query()
            ->withCount('roles')
            ->orderBy('group')
            ->orderBy('display_name');

        if ($search = trim((string) $request->string('search'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($group = trim((string) $request->string('group'))) {
            $query->where('group', $group);
        }

        $permissions = $query->paginate(25)->withQueryString();

        $groups = Permission::query()
            ->whereNotNull('group')
            ->where('group', '!=', '')
            ->distinct()
            ->orderBy('group')
            ->pluck('group');

        return view('admin.permissions.index', [
            'permissions' => $permissions,
            'groups' => $groups,
            'filters' => $request->only(['search', 'group']),
        ]);
    }

    public function show(Permission $permission): View
    {
        $this->authorize('view', $permission);

        return view('admin.permissions.show', [
            'permission' => $permission->load([
                'roles' => fn ($query) => $query->orderBy('display_name'),
            ]),
        ]);
    }
}
