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
        $canViewSensitive = $request->user()->can('viewSensitive', User::class);
        $filters = $request->validated();
        $users = $this->service->paginate($filters, $canViewSensitive);

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'overview' => $this->service->overview($filters, $canViewSensitive),
            'activityTrackingAvailable' => $this->service->sessionActivityAvailable(),
            'roles' => Role::query()->orderBy('display_name')->get(['name', 'display_name']),
            'canViewSensitive' => $canViewSensitive,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $canViewSensitive = $request->user()->can('viewSensitive', User::class);
        $user->load([
            'roles',
            'profile' => function ($query) use ($canViewSensitive): void {
                $columns = ['id', 'user_id', 'first_name', 'last_name'];
                if ($canViewSensitive) {
                    $columns[] = 'phone';
                }
                $query->select($columns);
            },
            'professionalProfile.skills',
        ]);

        return view('admin.users.show', [
            'user' => $user,
            'details' => $this->service->detail($user, $canViewSensitive),
            'activityTrackingAvailable' => $this->service->sessionActivityAvailable(),
            'returnQuery' => $request->query(),
            'canViewSensitive' => $canViewSensitive,
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
