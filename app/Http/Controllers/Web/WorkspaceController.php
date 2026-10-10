<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class WorkspaceController
{
    public function dashboard(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($user->isActive(), 403, 'Ce compte ne peut pas accéder aux espaces protégés.');

        $workspaces = $this->availableWorkspaces($user);

        abort_if($workspaces === [], 403, 'Aucun espace de travail autorisé n’est associé à ce compte.');

        if (count($workspaces) === 1) {
            return redirect()->route(array_key_first($workspaces) === 'admin'
                ? $workspaces['admin']
                : $workspaces[array_key_first($workspaces)]);
        }

        $activeWorkspace = $request->session()->get('active_workspace');

        if (is_string($activeWorkspace) && isset($workspaces[$activeWorkspace])) {
            return redirect()->route($workspaces[$activeWorkspace]);
        }

        return view('auth.choose-workspace', ['workspaces' => $workspaces]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $request->validate([
            'workspace' => ['required', 'string', 'in:client,professional,admin'],
        ]);

        $user = $request->user();

        abort_unless($user instanceof User && $user->isActive(), 403);

        $workspaces = $this->availableWorkspaces($user);
        $workspace = $request->string('workspace')->toString();

        abort_unless(isset($workspaces[$workspace]), 403, 'Vous ne disposez pas des autorisations nécessaires pour cet espace.');

        $request->session()->put('active_workspace', $workspace);
        $request->session()->regenerate();

        return redirect()->route($workspaces[$workspace]);
    }

    /**
     * Return only workspaces the authenticated user is currently authorized to use.
     *
     * @return array<string, string>
     */
    private function availableWorkspaces(User $user): array
    {
        $workspaces = [];

        if ($user->hasPermissionTo('admin.dashboard.view') || $user->hasPermissionTo('rbac.view')) {
            $workspaces['admin'] = $user->hasPermissionTo('admin.dashboard.view')
                ? 'admin.dashboard'
                : 'admin.rbac.dashboard';
        }

        if ($user->hasRole('professional')) {
            $workspaces['professional'] = 'professional.dashboard';
        }

        if ($user->hasRole('client')) {
            $workspaces['client'] = 'client.dashboard';
        }

        return $workspaces;
    }
}
