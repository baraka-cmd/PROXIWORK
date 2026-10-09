<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\View\View;

class RbacDashboardController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.rbac.index', [
            'metrics' => [
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'usersWithRoles' => User::has('roles')->count(),
                'assignments' => Role::withCount('users')->get()->sum('users_count'),
            ],
            'roleDistribution' => Role::query()
                ->withCount('users')
                ->orderByDesc('users_count')
                ->get(['id', 'name', 'display_name', 'users_count']),
        ]);
    }
}
