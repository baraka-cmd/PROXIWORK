<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Models\Role;
use App\Services\Admin\Role\AdminRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(
        private readonly AdminRoleService $service,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => $this->service->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.form', [
            'role' => null,
            'permissions' => $this->service->permissions(),
            'selectedPermissions' => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->service->create($request->validated(), $request);

        return redirect()->route('admin.roles.show', $role)
            ->with('success', 'Le rôle a été créé avec succès.');
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        return view('admin.roles.show', [
            'role' => $this->service->find($role),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.form', [
            'role' => $role->load('permissions'),
            'permissions' => $this->service->permissions(),
            'selectedPermissions' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->service->update($role, $request->validated(), $request);

        return redirect()->route('admin.roles.show', $role)
            ->with('success', 'Le rôle a été mis à jour.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);
        $this->service->delete($role, $request);

        return redirect()->route('admin.roles.index')
            ->with('success', 'Le rôle a été supprimé.');
    }
}
