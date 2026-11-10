<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqArticle;
use App\Models\FaqCategory;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.faq.index', [
            'articles' => FaqArticle::query()->with('category')->latest()->paginate(20),
            'categories' => FaqCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $article = FaqArticle::query()->create([
            ...$data,
            'slug' => Str::slug($data['slug'] ?: $data['question']).'-'.Str::lower(Str::random(5)),
            'status' => 'draft',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $audit->record('faq_article_created', $article, $request->user(), [], $request);

        return back()->with('success', 'Question enregistrée en brouillon.');
    }

    public function update(Request $request, FaqArticle $article, AuditLogService $audit): RedirectResponse
    {
        abort_unless($article->status !== 'archived', 409);
        $data = $this->validated($request);
        $article->update([...$data, 'slug' => Str::slug($data['slug'] ?: $data['question']), 'updated_by' => $request->user()->id]);
        $audit->record('faq_article_updated', $article, $request->user(), [], $request);

        return back()->with('success', 'Question mise à jour.');
    }

    public function publish(Request $request, FaqArticle $article, AuditLogService $audit): RedirectResponse
    {
        $article->update(['status' => 'published', 'published_at' => now()]);
        $audit->record('faq_article_published', $article, $request->user(), [], $request);

        return back()->with('success', 'Question publiée.');
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
