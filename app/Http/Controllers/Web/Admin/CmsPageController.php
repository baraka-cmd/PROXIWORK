<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\MediaAsset;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    private const CONTENT_FIELDS = [
        'title',
        'slug',
        'excerpt',
        'body',
        'seo_title',
        'meta_description',
        'og_image_path',
        'canonical_url',
        'is_indexable',
    ];

    public function index(): View
    {
        return view('admin.communication.pages.index', [
            'pages' => CmsPage::query()
                ->withCount(['versions as draft_versions_count' => fn ($query) => $query->where('status', 'draft')])
                ->latest()
                ->paginate(20),
            'mediaAssets' => MediaAsset::query()->latest()->limit(100)->get(['id', 'path', 'alt_text']),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['title']);

        $page = DB::transaction(function () use ($data, $request): CmsPage {
            $page = CmsPage::query()->create([
                ...$data,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $this->snapshot($page, $data, $request->user()->id);

            return $page;
        });
        $audit->record('cms_page_created', $page, $request->user(), ['slug' => $page->slug], $request);

        return back()->with('success', 'Page enregistrée en brouillon.');
    }

    public function update(Request $request, CmsPage $page, AuditLogService $audit): RedirectResponse
    {
        abort_unless($page->status !== 'archived', 409);
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['title']);

        DB::transaction(function () use ($page, $data, $request): void {
            $locked = CmsPage::query()->lockForUpdate()->findOrFail($page->id);

            // Keep the currently published page live while a new revision is being prepared.
            if ($locked->status !== 'published') {
                $locked->update($data);
            }

            $this->snapshot($locked, $data, $request->user()->id);
        });
        $audit->record('cms_page_revision_created', $page, $request->user(), [], $request);

        return back()->with('success', 'Nouvelle version enregistrée en brouillon. La page publiée reste inchangée.');
    }

    public function publish(Request $request, CmsPage $page, AuditLogService $audit): RedirectResponse
    {
        DB::transaction(function () use ($page, $request): void {
            $locked = CmsPage::query()->lockForUpdate()->findOrFail($page->id);
            $version = $locked->versions()
                ->where('status', 'draft')
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();
            $content = Arr::only($version->content, self::CONTENT_FIELDS);

            $locked->update([
                ...$content,
                'status' => 'published',
                'published_by' => $request->user()->id,
                'published_at' => now(),
            ]);

            $locked->versions()->where('status', 'published')->update(['status' => 'superseded']);
            $version->update(['status' => 'published', 'published_at' => now()]);
        });
        $audit->record('cms_page_published', $page, $request->user(), [], $request);

        return back()->with('success', 'La nouvelle version de la page est publiée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:50000'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'og_image_path' => ['nullable', 'string', 'max:500', 'exists:media_assets,path'],
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'is_indexable' => ['sometimes', 'boolean'],
        ]) + ['is_indexable' => $request->boolean('is_indexable')];
    }

    private function snapshot(CmsPage $page, array $content, int $userId): void
    {
        $version = (int) $page->versions()->max('version') + 1;
        $page->versions()->create([
            'version' => $version,
            'content' => Arr::only($content, self::CONTENT_FIELDS),
            'created_by' => $userId,
            'status' => 'draft',
        ]);
    }
}
