<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}
    public function show(Request $request): ProfileResource
    {
        abort_unless($request->user()->hasPermissionTo('profiles.view'), 403);

        $profile = $request->user()->profile()->firstOrFail();

        $this->authorize('view', $profile);

        return (new ProfileResource($profile))->additional([
            'message' => 'Profil récupéré avec succès.',
            'meta' => [],
        ]);
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $profile = $request->user()->profile()->firstOrFail();

        $this->authorize('update', $profile);

        $profile->update($request->validated());

        $this->auditLogService->record('profile_updated', $profile, $request->user(), [], $request);
        $request->user()->notify(new AccountActivityNotification(
            'Profil mis à jour',
            'Les informations de votre profil ont été mises à jour.',
            'profile_updated',
        ));

        return (new ProfileResource($profile->refresh()))->additional([
            'message' => 'Profil mis à jour avec succès.',
            'meta' => [],
        ]);
    }
}
