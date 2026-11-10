<?php

declare(strict_types=1);

namespace App\Services\Admin\Communication;

use App\Jobs\Notifications\DispatchCommunicationCampaign;
use App\Models\CommunicationCampaign;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunicationCampaignService
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function create(array $data, Request $request): CommunicationCampaign
    {
        $audience = $data['audience'] ?? ['role' => 'all'];
        $role = $audience['role'] ?? 'all';
        $audienceCount = User::query()
            ->where('account_status', 'active')
            ->whereNotNull('email_verified_at')
            ->when(in_array($role, ['client', 'professional'], true), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->when(($audience['recent_days'] ?? null) !== null, fn ($q) => $q->where('created_at', '>=', now()->subDays((int) $audience['recent_days'])))
            ->count();

        return DB::transaction(function () use ($data, $request, $audience, $audienceCount): CommunicationCampaign {
            $campaign = CommunicationCampaign::create([
                'title' => $data['title'],
                'body' => $data['body'],
                'audience' => $audience,
                'channels' => $data['channels'],
                'priority' => $data['priority'] ?? 'normal',
                'status' => isset($data['scheduled_at']) ? 'scheduled' : 'draft',
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'audience_count' => $audienceCount,
                'idempotency_key' => (string) Str::uuid(),
                'created_by' => $request->user()?->getAuthIdentifier(),
            ]);
            $this->audit->record('communication_campaign_created', $campaign, $request->user(), [
                'audience' => $campaign->audience,
                'channels' => $campaign->channels,
                'audience_count' => $audienceCount,
            ], $request);

            return $campaign;
        });
    }

    public function dispatch(CommunicationCampaign $campaign, Request $request): void
    {
        DB::transaction(function () use ($campaign, $request): void {
            $locked = CommunicationCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            abort_unless(in_array($locked->status, ['draft', 'scheduled'], true), 409);
            $locked->update(['status' => 'dispatching', 'scheduled_at' => null]);
            $this->audit->record('communication_campaign_dispatch_requested', $locked, $request->user(), [], $request);
            DispatchCommunicationCampaign::dispatch((int) $locked->getKey())->afterCommit();
        });
    }
}
