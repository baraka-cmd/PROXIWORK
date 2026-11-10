<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
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
        return view('admin.communication.campaigns.index', ['campaigns' => CommunicationCampaign::query()->latest()->paginate(20)]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', 'in:all,client,professional'],
        ]);

        $campaign = CommunicationCampaign::query()->create([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'channel' => 'database',
            'audience_rules' => ['audience' => $data['audience'], 'account_status' => 'active'],
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $audit->record('communication_campaign_created', $campaign, $request->user(), ['audience' => $data['audience']], $request);

        return back()->with('success', 'Campagne enregistrée en brouillon.');
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
        $count = $this->audienceQuery($campaign)->count();
        abort_if($count === 0, 422, 'Aucun destinataire actif ne correspond à ce segment.');

        DB::transaction(function () use ($campaign, $request, $count): void {
            $locked = CommunicationCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            abort_unless($locked->status === 'draft', 409);
            $query = $this->audienceQuery($locked);
            $query->select('users.id')->orderBy('users.id')->chunkById(500, function ($users) use ($locked): void {
                $rows = $users->map(fn ($user) => [
                    'campaign_id' => $locked->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();
                CommunicationCampaignRecipient::query()->insertOrIgnore($rows);
            }, 'users.id');
            $locked->update(['status' => 'queued', 'recipient_count' => $count, 'started_at' => now()]);
        });

        SendCommunicationCampaign::dispatch($campaign->id)->afterCommit();
        $audit->record('communication_campaign_queued', $campaign, $request->user(), ['recipient_count' => $count], $request);

        return back()->with('success', 'Campagne mise en file d’attente. Les destinataires ont été figés pour cette campagne.');
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

        return User::query()
            ->where('account_status', 'active')
            ->whereHas('roles', fn ($roles) => $roles->whereIn('name', match ($audience) {
                'client' => ['client'],
                'professional' => ['professional'],
                default => ['client', 'professional'],
            }))
            ->whereNotNull('email_verified_at');
    }
}
