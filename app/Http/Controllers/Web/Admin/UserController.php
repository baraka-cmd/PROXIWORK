<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AdminUserActivateRequest;
use App\Http\Requests\Admin\User\AdminUserIndexRequest;
use App\Http\Requests\Admin\User\AdminUserSuspendRequest;
use App\Http\Requests\Admin\User\AdminUserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\User\AdminUserService;
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
            'overview' => $this->service->overview($request->validated()),
            'activityTrackingAvailable' => $this->service->sessionActivityAvailable(),
            'roles' => Role::query()->orderBy('display_name')->get(['name', 'display_name']),
            'canViewSensitive' => $request->user()->can('viewSensitive', User::class),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'profile', 'professionalProfile.skills', 'suspendedBy']);

        return view('admin.users.show', [
            'user' => $user,
            'details' => $this->service->detail($user),
            'activityTrackingAvailable' => $this->service->sessionActivityAvailable(),
            'returnQuery' => $request->query(),
            'canViewSensitive' => $request->user()->hasPermissionTo('admin.users.view_sensitive'),
        ]);
    }

    public function update(AdminUserUpdateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->service->updateIdentity($user, $request->user(), $request->validated(), $request);

        return back()->with('success', 'L’identité du compte a été corrigée et journalisée.');
    }

    public function suspend(AdminUserSuspendRequest $request, User $user): RedirectResponse
    {
        $this->authorize('suspend', $user);
        $this->service->suspend($user, $request->user(), $request->validated(), $request);

        return back()->with('success', 'Le compte utilisateur a été suspendu.');
    }

    public function activate(AdminUserActivateRequest $request, User $user): RedirectResponse
    {
        $this->authorize('activate', $user);
        $this->service->activate($user, $request->user(), (string) $request->validated('reason'), $request);

        return back()->with('success', 'Le compte utilisateur a été réactivé.');
    }
}
