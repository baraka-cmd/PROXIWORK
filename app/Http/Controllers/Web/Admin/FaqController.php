<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FaqController extends Controller
{
    private const CONTENT_FIELDS = [
        'faq_category_id',
        'question',
        'slug',
        'answer',
        'audience',
        'locale',
        'sort_order',
    ];

    public function index(): View
    {
        return view('admin.communication.faq.index', [
            'articles' => FaqArticle::query()
                ->with('category')
                ->withCount('versions')
                ->withCount(['versions as draft_versions_count' => fn ($query) => $query->where('status', 'draft')])
                ->latest()
                ->paginate(20),
            'categories' => FaqCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
        ]);
        $slug = Str::slug(($data['slug'] ?? '') ?: $data['name']);
        $category = FaqCategory::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'sort_order' => ((int) FaqCategory::query()->max('sort_order')) + 10,
            'is_active' => true,
        ]);
        $audit->record('faq_category_created', $category, $request->user(), [], $request);

        return back()->with('success', 'Catégorie FAQ créée.');
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug(($data['slug'] ?? '') ?: $data['question']).'-'.Str::lower(Str::random(5));

        $article = DB::transaction(function () use ($data, $request): FaqArticle {
            $article = FaqArticle::query()->create([
                ...$data,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $this->snapshot($article, $data, $request->user()->id);

            return $article;
        });
        $audit->record('faq_article_created', $article, $request->user(), [], $request);

        return back()->with('success', 'Question enregistrée en brouillon.');
    }

    public function update(Request $request, FaqArticle $article, AuditLogService $audit): RedirectResponse
    {
        abort_unless($article->status !== 'archived', 409);
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['slug'] ?: $data['question']).'-'.Str::lower(Str::random(5));

        DB::transaction(function () use ($article, $data, $request): void {
            $locked = FaqArticle::query()->lockForUpdate()->findOrFail($article->id);
            if ($locked->status !== 'published') {
                $locked->update([...$data, 'updated_by' => $request->user()->id]);
            }
            $this->snapshot($locked, $data, $request->user()->id);
        });
        $audit->record('faq_article_revision_created', $article, $request->user(), [], $request);

        return back()->with('success', 'Nouvelle version enregistrée en brouillon.');
    }

    public function publish(Request $request, FaqArticle $article, AuditLogService $audit): RedirectResponse
    {
        DB::transaction(function () use ($article): void {
            $locked = FaqArticle::query()->lockForUpdate()->findOrFail($article->id);
            $version = $locked->versions()
                ->where('status', 'draft')
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();
            $content = Arr::only($version->content, self::CONTENT_FIELDS);

            $locked->update([...$content, 'status' => 'published', 'published_at' => now()]);
            $locked->versions()->whereIn('status', ['draft', 'published'])->whereKeyNot($version->id)->update(['status' => 'superseded']);
            $version->update(['status' => 'published', 'published_at' => now()]);
        });
        $audit->record('faq_article_published', $article, $request->user(), [], $request);

        return back()->with('success', 'La nouvelle version de la réponse est publiée.');
    }

    private function snapshot(FaqArticle $article, array $content, int $userId): void
    {
        $version = (int) $article->versions()->max('version') + 1;
        $article->versions()->create([
            'version' => $version,
            'content' => Arr::only($content, self::CONTENT_FIELDS),
            'created_by' => $userId,
            'status' => 'draft',
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'faq_category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200'],
            'answer' => ['required', 'string', 'max:30000'],
            'audience' => ['required', 'in:all,client,professional'],
            'locale' => ['required', 'in:fr,en'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]) + ['sort_order' => (int) $request->input('sort_order', 0)];
    }
}
