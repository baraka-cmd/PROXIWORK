<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\AdminUserIndexRequest;
use App\Http\Resources\Admin\User\AdminUserResource;
use App\Models\User;
use App\Services\Admin\User\AdminUserService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $service,
    ) {}

    public function index(AdminUserIndexRequest $request): AnonymousResourceCollection
    {
        $users = $this->service->paginate($request->validated());

        return AdminUserResource::collection($users)->additional([
            'message' => 'Utilisateurs récupérés avec succès.',
            'meta' => ['scope' => 'admin.users'],
        ]);
    }

    public function show(User $user): AdminUserResource
    {
        $this->authorize('view', $user);

        return (new AdminUserResource($user->load('roles')))->additional([
            'message' => 'Utilisateur récupéré avec succès.',
            'meta' => ['scope' => 'admin.users'],
        ]);
    }

    public function suspend(User $user, Request $request): AdminUserResource
    {
        $this->authorize('suspend', $user);

        $updated = $this->service->suspend($user, $request->user(), $request);

        return (new AdminUserResource($updated))->additional([
            'message' => 'Utilisateur suspendu avec succès.',
            'meta' => ['scope' => 'admin.users'],
        ]);
    }

    public function activate(User $user, Request $request): AdminUserResource
    {
        $this->authorize('activate', $user);

        $updated = $this->service->activate($user, $request->user(), $request);

        return (new AdminUserResource($updated))->additional([
            'message' => 'Utilisateur réactivé avec succès.',
            'meta' => ['scope' => 'admin.users'],
        ]);
    }
}
