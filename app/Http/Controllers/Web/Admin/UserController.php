<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\SyncUserRolesRequest;
use App\Http\Requests\Admin\User\AdminUserIndexRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\User\AdminUserService;
use App\Services\Rbac\UserRoleAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $service,
    ) {}

    public function index(AdminUserIndexRequest $request): View
    {
        $users = $this->service->paginate($request->validated());

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $request->validated(),
        ]);
    }

    public function show(User $user, Request $request): View
    {
        $this->authorize('view', $user);

        $canManageRoles = $request->user()?->hasPermissionTo('admin.dashboard.view') ?? false;

        return view('admin.users.show', [
            'user' => $user->load(['roles', 'profile', 'professionalProfile']),
            'assignableRoles' => $canManageRoles
                ? Role::query()->orderBy('display_name')->get(['id', 'name', 'display_name', 'is_system'])
                : collect(),
            'canManageRoles' => $canManageRoles,
        ]);
    }

    public function updateRoles(
        SyncUserRolesRequest $request,
        User $user,
        UserRoleAssignmentService $roleAssignmentService,
    ): RedirectResponse {
        abort_if($request->user()?->is($user), 403, 'Vous ne pouvez pas modifier vos propres rôles.');

        $roleAssignmentService->sync(
            $user,
            $request->user(),
            collect($request->validated('role_ids'))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            $request,
        );

        return back()->with('success', 'Les rôles de cet utilisateur ont été mis à jour. Ses anciennes sessions ont été révoquées.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);
        $this->service->suspend($user, $request->user(), $request);

        return back()->with('success', 'Le compte utilisateur a été suspendu.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);
        $this->service->activate($user, $request->user(), $request);

        return back()->with('success', 'Le compte utilisateur a été réactivé.');
    }
}
