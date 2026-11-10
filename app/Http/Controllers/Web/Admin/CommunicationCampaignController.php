<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Models\CommunicationSegment;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CommunicationCampaignController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.campaigns.index', [
            'campaigns' => CommunicationCampaign::query()->latest()->paginate(20),
            'segments' => CommunicationSegment::query()->latest()->get(),
        ]);
    }

    public function show(CommunicationCampaign $campaign): View
    {
        return view('admin.communication.campaigns.show', [
            'campaign' => $campaign,
            'recipients' => $campaign->recipients()->with('user:id,name')->latest('id')->paginate(30),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['nullable', 'in:all,client,professional'],
            'segment_id' => ['nullable', 'integer', 'exists:communication_segments,id'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        $campaign = CommunicationCampaign::query()->create([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'channel' => 'database',
            'audience_rules' => isset($data['segment_id'])
                ? CommunicationSegment::query()->findOrFail($data['segment_id'])->rules
                : ['audience' => ($data['audience'] ?? 'all'), 'account_status' => 'active'],
            'communication_segment_id' => $data['segment_id'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $audit->record('communication_campaign_created', $campaign, $request->user(), [
            'audience' => $campaign->audience_rules['audience'] ?? 'all',
            'segment_id' => $campaign->communication_segment_id,
        ], $request);

        return back()->with('success', 'Campagne enregistrée en brouillon.');
    }

    public function storeSegment(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'audience' => ['required', 'in:all,client,professional'],
        ]);
        $segment = CommunicationSegment::query()->create([
            'name' => $data['name'],
            'rules' => ['audience' => $data['audience'], 'account_status' => 'active'],
            'created_by' => $request->user()->id,
        ]);
        $audit->record('communication_segment_created', $segment, $request->user(), ['audience' => $data['audience']], $request);

        return back()->with('success', 'Segment enregistré.');
    }

    public function preview(CommunicationCampaign $campaign): View
    {
        abort_unless($campaign->status === 'draft', 409);

        return view('admin.communication.campaigns.preview', [
            'campaign' => $campaign,
            'recipientCount' => $this->audienceQuery($campaign)->count(),
        ]);
    }

    public function dispatch(Request $request, CommunicationCampaign $campaign, AuditLogService $audit): RedirectResponse
    {
        abort_unless($campaign->status === 'draft', 409);

        $dispatchInfo = DB::transaction(function () use ($campaign): array {
            $locked = CommunicationCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            abort_unless($locked->status === 'draft', 409);

            $query = $this->audienceQuery($locked);
            $query->select('users.id as id')
                ->orderBy('users.id')
                ->chunkById(500, function ($users) use ($locked): void {
                    $rows = $users->map(function ($user) use ($locked): array {
                        return [
                            'campaign_id' => $locked->id,
                            'user_id' => $user->id,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->all();
                    CommunicationCampaignRecipient::query()->insertOrIgnore($rows);
                }, 'users.id', 'id');

            $actualCount = $locked->recipients()->count();
            abort_if($actualCount === 0, 422, 'Aucun destinataire actif ne correspond à ce segment.');
            abort_if(
                $actualCount > 100 && ($locked->approved_by === null || $locked->approved_by === $locked->created_by),
                409,
                'Une campagne de plus de 100 destinataires doit être approuvée par une autre personne.',
            );

            $scheduledAt = $locked->scheduled_at?->isFuture() ? $locked->scheduled_at : null;
            $locked->update([
                'status' => $scheduledAt ? 'scheduled' : 'queued',
                'recipient_count' => $actualCount,
                'started_at' => now(),
            ]);

            return ['recipient_count' => $actualCount, 'scheduled_at' => $scheduledAt];
        });

        $pendingDispatch = SendCommunicationCampaign::dispatch($campaign->id);
        if ($dispatchInfo['scheduled_at'] !== null) {
            $pendingDispatch->delay($dispatchInfo['scheduled_at']);
        }
        $pendingDispatch->afterCommit();
        $audit->record('communication_campaign_queued', $campaign, $request->user(), [
            'recipient_count' => $dispatchInfo['recipient_count'],
            'scheduled_at' => $dispatchInfo['scheduled_at']?->toIso8601String(),
        ], $request);

        return back()->with('success', $dispatchInfo['scheduled_at']
            ? 'Campagne programmée. Les destinataires ont été figés.'
            : 'Campagne mise en file d’attente. Les destinataires ont été figés.');
    }

    public function approve(Request $request, CommunicationCampaign $campaign, AuditLogService $audit): RedirectResponse
    {
        abort_unless($campaign->status === 'draft', 409);
        abort_if($campaign->created_by === $request->user()->id, 403, 'L’approbateur doit être une autre personne.');
        $campaign->update(['approved_by' => $request->user()->id]);
        $audit->record('communication_campaign_approved', $campaign, $request->user(), [], $request);

        return back()->with('success', 'Campagne approuvée.');
    }

    private function audienceQuery(CommunicationCampaign $campaign)
    {
        $audience = $campaign->audience_rules['audience'] ?? 'all';

        $roleNames = match ($audience) {
            'client' => ['client'],
            'professional' => ['professional'],
            default => ['client', 'professional'],
        };

        $roleIds = DB::table('roles')->select('id')->whereIn('name', $roleNames);
        $userIds = DB::table('role_user')->select('user_id')->whereIn('role_id', $roleIds);

        return User::query()
            ->where('account_status', 'active')
            ->whereIn('id', $userIds)
;
    }
}
