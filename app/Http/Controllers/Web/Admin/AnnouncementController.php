<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.announcements.index', [
            'announcements' => Announcement::query()->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $announcement = Announcement::query()->create([
            ...$data,
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $audit->record('announcement_created', $announcement, $request->user(), [], $request);

        return back()->with('success', 'Annonce enregistrée en brouillon.');
    }

    public function update(Request $request, Announcement $announcement, AuditLogService $audit): RedirectResponse
    {
        abort_unless(in_array($announcement->status, ['draft', 'scheduled'], true), 409);
        $announcement->update($this->validated($request));
        $audit->record('announcement_updated', $announcement, $request->user(), [], $request);

        return back()->with('success', 'Annonce mise à jour.');
    }

    public function publish(Request $request, Announcement $announcement, AuditLogService $audit): RedirectResponse
    {
        abort_unless($announcement->status === 'draft' || $announcement->status === 'scheduled', 409);
        abort_if($announcement->ends_at && $announcement->ends_at->isPast(), 422, 'La date de fin est déjà dépassée.');

        DB::transaction(function () use ($announcement, $request): void {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            $locked->update([
                'status' => $locked->starts_at?->isFuture() ? 'scheduled' : 'published',
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);
        });
        $audit->record('announcement_published', $announcement, $request->user(), [], $request);

        return back()->with('success', 'Annonce publiée ou programmée.');
    }

    public function archive(Request $request, Announcement $announcement, AuditLogService $audit): RedirectResponse
    {
        $announcement->update(['status' => 'archived', 'is_pinned' => false]);
        $audit->record('announcement_archived', $announcement, $request->user(), [], $request);

        return back()->with('success', 'Annonce archivée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'type' => ['required', 'in:information,alert,official'],
            'placement' => ['required', 'in:announcements,homepage,client,professional'],
            'is_pinned' => ['sometimes', 'boolean'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:500', 'regex:/^\/(?!\/)[^\\]*$/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]) + ['is_pinned' => $request->boolean('is_pinned')];
    }
}
