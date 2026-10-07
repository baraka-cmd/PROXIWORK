<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AdminUserIndexRequest;
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
        ]);
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            'user' => $user->load(['roles', 'profile', 'professionalProfile']),
        ]);
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
