<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function dashboard(Request $request): RedirectResponse|View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workspaces = $this->workspacesFor($user);
        abort_if($workspaces->isEmpty(), 403, 'Aucun espace de travail n’est associé à ce compte.');

        $activeWorkspace = $request->session()->get('active_workspace');

        if (is_string($activeWorkspace) && $workspaces->has($activeWorkspace)) {
            return redirect()->route($workspaces->get($activeWorkspace)['route']);
        }

        if ($workspaces->count() === 1) {
            $workspace = $workspaces->first();
            $request->session()->put('active_workspace', $workspace['key']);

            return redirect()->route($workspace['route']);
        }

        return view('auth.workspaces', [
            'workspaces' => $workspaces,
        ]);
    }

    public function switch(Request $request, string $workspace): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $workspaces = $this->workspacesFor($user);
        abort_unless($workspaces->has($workspace), 403, 'Cet espace n’est pas autorisé pour ce compte.');

        $request->session()->put('active_workspace', $workspace);

        return redirect()->route($workspaces->get($workspace)['route']);
    }

    /**
     * @return Collection<string, array{key: string, title: string, description: string, route: string}>
     */
    private function workspacesFor(User $user): Collection
    {
        $workspaces = collect();

        if ($user->hasRole('client')) {
            $workspaces->put('client', [
                'key' => 'client',
                'title' => 'Espace client',
                'description' => 'Rechercher des professionnels et suivre vos demandes, devis et commandes.',
                'route' => 'client.dashboard',
            ]);
        }

        if ($user->hasRole('professional')) {
            $workspaces->put('professional', [
                'key' => 'professional',
                'title' => 'Espace professionnel',
                'description' => 'Gérer votre dossier, vos services, vos demandes et votre activité.',
                'route' => 'professional.dashboard',
            ]);
        }

        if ($user->hasPermissionTo('admin.dashboard.view')) {
            $workspaces->put('admin', [
                'key' => 'admin',
                'title' => 'Administration',
                'description' => 'Accéder aux outils administratifs autorisés pour votre compte.',
                'route' => 'admin.dashboard',
            ]);
        } elseif ($user->hasPermissionTo('rbac.view')) {
            $workspaces->put('admin', [
                'key' => 'admin',
                'title' => 'Administration',
                'description' => 'Accéder à la gestion des rôles et permissions autorisée pour votre compte.',
                'route' => 'admin.rbac.dashboard',
            ]);
        }

        return $workspaces;
    }
}
