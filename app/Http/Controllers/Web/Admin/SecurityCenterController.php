<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityAlert;
use App\Models\SecuritySession;
use App\Models\SensitiveIntervention;
use App\Services\Audit\AuditLogService;
use App\Services\Audit\SecurityAlertService;
use App\Services\Audit\SecuritySessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SecurityCenterController extends Controller
{
    public function events(): View
    {
        $events = AuditLog::query()
            ->with('user:id,name,email')
            ->whereIn('category', ['security', 'authentication'])
            ->latest('id')
            ->paginate(50);

        return view('admin.security.events', compact('events'));
    }

    public function anomalies(): View
    {
        $alerts = SecurityAlert::query()
            ->with(['user:id,name,email', 'assignee:id,name', 'sourceEvent'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('last_seen_at')
            ->paginate(30);

        return view('admin.security.anomalies', compact('alerts'));
    }

    public function sessions(): View
    {
        $sessions = SecuritySession::query()
            ->with(['user:id,name,email', 'revoker:id,name'])
            ->latest('last_seen_at')
            ->paginate(50);

        return view('admin.security.sessions', compact('sessions'));
    }

    public function interventions(): View
    {
        $interventions = SensitiveIntervention::query()
            ->with(['actor:id,name,email'])
            ->latest('created_at')
            ->paginate(50);

        return view('admin.security.interventions', compact('interventions'));
    }

    public function revokeSession(Request $request, SecuritySession $securitySession, SecuritySessionService $sessions): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $sessions->revoke($securitySession, $request->user(), $data['reason'], $request);

        return back()->with('success', 'La session a été révoquée côté serveur.');
    }

    public function resolveAlert(Request $request, SecurityAlert $securityAlert, SecurityAlertService $alerts, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:investigating,confirmed,false_positive,resolved,closed'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        DB::transaction(function () use ($securityAlert, $alerts, $audit, $request, $data): void {
            $updated = $alerts->resolve($securityAlert, (int) $request->user()->getKey(), $data['status'], $data['note']);
            $audit->recordEvent(
                'security.alert.'.$data['status'],
                $updated,
                $request->user(),
                ['alert_id' => $updated->getKey(), 'rule_key' => $updated->rule_key],
                $request,
                category: 'security',
                reason: $data['note'],
                severity: $updated->severity,
            );
        }, attempts: 3);

        return back()->with('success', 'Le traitement de l’alerte a été enregistré.');
    }
}
