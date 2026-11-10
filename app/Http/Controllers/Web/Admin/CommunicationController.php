<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CommunicationCampaign;
use App\Services\Admin\Communication\CommunicationCampaignService;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function __construct(
        private readonly CommunicationCampaignService $campaigns,
        private readonly AuditLogService $audit,
    ) {}

    public function announcements(): View
    {
        return view('admin.communication.announcements.index', ['announcements' => Announcement::query()->latest()->paginate(25)]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['required', 'in:information,maintenance,security,institutional,promotional,urgent'],
            'audience' => ['required', 'in:all,client,professional'],
            'priority' => ['required', 'in:normal,important,critical'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $base = Str::slug($data['title']) ?: 'annonce';
        $announcement = Announcement::create($data + [
            'slug' => $base.'-'.Str::lower(Str::random(6)),
            'status' => 'draft',
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit->record('announcement_created', $announcement, $request->user(), ['audience' => $announcement->audience], $request);

        return back()->with('success', 'Annonce créée en brouillon.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($announcement->status === 'draft', 409, 'Une annonce publiée ne peut pas être modifiée silencieusement. Créez une nouvelle annonce.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'category' => ['required', 'in:information,maintenance,security,institutional,promotional,urgent'],
            'audience' => ['required', 'in:all,client,professional'],
            'priority' => ['required', 'in:normal,important,critical'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
        $announcement->update($data);
        $this->audit->record('announcement_updated', $announcement, $request->user(), ['audience' => $announcement->audience], $request);

        return back()->with('success', 'Annonce modifiée.');
    }

    public function publishAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_if($announcement->status === 'archived', 409);
        $announcement->update([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit->record('announcement_published', $announcement, $request->user(), [
            'audience' => $announcement->audience,
            'starts_at' => $announcement->starts_at?->toIso8601String(),
            'ends_at' => $announcement->ends_at?->toIso8601String(),
        ], $request);

        return back()->with('success', 'Annonce publiée selon son audience et sa période de visibilité.');
    }

    public function archiveAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update(['status' => 'archived']);
        $this->audit->record('announcement_archived', $announcement, $request->user(), [], $request);

        return back()->with('success', 'Annonce archivée.');
    }

    public function campaigns(): View
    {
        return view('admin.communication.campaigns.index', ['campaigns' => CommunicationCampaign::query()->latest()->paginate(25)]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'role' => ['required', 'in:all,client,professional'],
            'recent_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'in:mail,push,sms'],
            'priority' => ['required', 'in:normal,important'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'confirm_broadcast' => ['accepted'],
        ]);
        $audience = ['role' => $data['role']];
        if (isset($data['recent_days'])) {
            $audience['recent_days'] = (int) $data['recent_days'];
        }
        $this->campaigns->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $audience,
            'channels' => array_values(array_unique($data['channels'])),
            'priority' => $data['priority'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ], $request);

        return redirect()->route('admin.communication.campaigns')->with('success', 'Campagne enregistrée. Vérifiez l’audience estimée avant diffusion.');
    }

    public function dispatchCampaign(Request $request, CommunicationCampaign $campaign): RedirectResponse
    {
        $this->campaigns->dispatch($campaign, $request);

        return back()->with('success', 'La campagne a été placée dans la file de traitement.');
    }
}
