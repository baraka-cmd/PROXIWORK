<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): ProfileResource
    {
        abort_unless($request->user()->hasPermissionTo('profiles.view'), 403);

        $profile = $request->user()->profile()->firstOrFail();

        $this->authorize('view', $profile);

        return (new ProfileResource($profile))->additional([
            'message' => 'Profil récupéré avec succès.',
            'meta' => [],
            'meta' => [],
        ]);
    }

    public function update(UpdateProfileRequest $request): ProfileResource
    {
        $profile = $request->user()->profile()->firstOrFail();

        $this->authorize('update', $profile);

        $profile->update($request->validated());

        return (new ProfileResource($profile->refresh()))->additional([
            'message' => 'Profil mis à jour avec succès.',
            'meta' => [],
            'meta' => [],
        ]);
    }
}
