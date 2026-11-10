<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CmsPageController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.pages.index', ['pages' => CmsPage::query()->latest()->paginate(20)]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $page = DB::transaction(function () use ($data, $request): CmsPage {
            $page = CmsPage::query()->create([
                ...$data,
                'slug' => Str::slug($data['slug'] ?: $data['title']),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $this->snapshot($page, $request->user()->id);

            return $page;
        });
        $audit->record('cms_page_created', $page, $request->user(), ['slug' => $page->slug], $request);

        return back()->with('success', 'Page enregistrée en brouillon.');
    }

    public function update(Request $request, CmsPage $page, AuditLogService $audit): RedirectResponse
    {
        abort_unless($page->status !== 'archived', 409);
        $data = $this->validated($request);
        DB::transaction(function () use ($page, $data, $request): void {
            $locked = CmsPage::query()->lockForUpdate()->findOrFail($page->id);
            $locked->update([...$data, 'slug' => Str::slug($data['slug'] ?: $data['title'])]);
            $this->snapshot($locked, $request->user()->id);
        });
        $audit->record('cms_page_updated', $page, $request->user(), [], $request);

        return back()->with('success', 'Page mise à jour et nouvelle version historisée.');
    }

    public function publish(Request $request, CmsPage $page, AuditLogService $audit): RedirectResponse
    {
        DB::transaction(function () use ($page, $request): void {
            $locked = CmsPage::query()->lockForUpdate()->findOrFail($page->id);
            $locked->update(['status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);
        });
        $audit->record('cms_page_published', $page, $request->user(), [], $request);

        return back()->with('success', 'Page publiée.');
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
            'canonical_url' => ['nullable', 'url', 'max:500'],
            'is_indexable' => ['sometimes', 'boolean'],
        ]) + ['is_indexable' => $request->boolean('is_indexable')];
    }

    private function snapshot(CmsPage $page, int $userId): void
    {
        $version = (int) $page->versions()->max('version') + 1;
        $page->versions()->create([
            'version' => $version,
            'content' => $page->only(['title','slug','excerpt','body','seo_title','meta_description','canonical_url','is_indexable']),
            'created_by' => $userId,
        ]);
    }
}
