<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CmsPage;
use App\Models\FaqArticle;
use App\Models\InstitutionalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect([
            ['loc' => route('home'), 'lastmod' => now()->toAtomString()],
            ['loc' => route('public.announcements'), 'lastmod' => now()->toAtomString()],
            ['loc' => route('public.faq'), 'lastmod' => now()->toAtomString()],
        ]);

        CmsPage::query()->published()->where('is_indexable', true)->get()->each(function (CmsPage $page) use ($urls): void {
            $urls->push([
                'loc' => $page->canonical_url ?: route('public.pages.show', $page->slug),
                'lastmod' => ($page->updated_at ?? $page->published_at)->toAtomString(),
            ]);
        });

        FaqArticle::query()->published()->get()->each(function (FaqArticle $article) use ($urls): void {
            $urls->push([
                'loc' => route('public.faq').'#faq-'.$article->id,
                'lastmod' => ($article->updated_at ?? $article->published_at)->toAtomString(),
            ]);
        });

        InstitutionalDocument::query()->where('status', 'published')->get()->each(function (InstitutionalDocument $document) use ($urls): void {
            $urls->push([
                'loc' => route('public.legal.show', $document->slug),
                'lastmod' => $document->updated_at->toAtomString(),
            ]);
        });

        Announcement::query()->visible()->where('placement', 'announcements')->get()->each(function (Announcement $announcement) use ($urls): void {
            $urls->push([
                'loc' => route('public.announcements').'#announcement-'.$announcement->id,
                'lastmod' => ($announcement->updated_at ?? $announcement->published_at)->toAtomString(),
            ]);
        });

        return response()->view('public.content.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function announcements(): View
    {
        return view('public.content.announcements', [
            'announcements' => Announcement::query()->visible()->where('placement', 'announcements')->orderByDesc('is_pinned')->orderByDesc('published_at')->paginate(15),
        ]);
    }

    public function page(string $slug): View
    {
        $page = CmsPage::query()->published()->where('slug', $slug)->firstOrFail();

        return view('public.content.page', ['page' => $page]);
    }

    public function faq(Request $request): View
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? null;
        $faqLocale = app()->getLocale();
        if (! FaqArticle::query()->published()->where('locale', $faqLocale)->exists()) {
            $faqLocale = 'fr';
        }
        $articles = FaqArticle::query()->published()
            ->with('category')
            ->whereIn('audience', ['all', auth()->user()?->hasRole('professional') ? 'professional' : (auth()->user()?->hasRole('client') ? 'client' : 'all')])
            ->where('locale', $faqLocale)
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('question', 'like', '%'.$search.'%')
                ->orWhere('answer', 'like', '%'.$search.'%')))
            ->orderBy('sort_order')->paginate(20)->withQueryString();

        return view('public.content.faq', ['articles' => $articles, 'search' => $search]);
    }

    public function feedback(Request $request, FaqArticle $article): RedirectResponse
    {
        abort_unless($article->status === 'published', 404);
        $data = $request->validate(['helpful' => ['required', 'boolean']]);
        $identity = $request->user()
            ? 'user:'.$request->user()->id
            : 'guest:'.$request->ip().'|'.(string) $request->userAgent();
        $fingerprint = hash('sha256', $identity);
        $inserted = DB::table('content_feedback')->insertOrIgnore([
            'faq_article_id' => $article->id,
            'fingerprint' => $fingerprint,
            'helpful' => (bool) $data['helpful'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 1) {
            $article->increment($data['helpful'] ? 'helpful_yes' : 'helpful_no');
        }

        return back()->with('success', 'Merci pour votre retour.');
    }

    public function document(string $slug): View
    {
        $document = InstitutionalDocument::query()->where('slug', $slug)->where('status', 'published')
            ->with(['versions' => fn ($query) => $query->where('status', 'published')->latest('version')])
            ->firstOrFail();
        $version = $document->versions->first();
        abort_if($version === null || ($version->effective_at && $version->effective_at->isFuture()), 404);

        return view('public.content.document', ['document' => $document, 'version' => $version]);
    }

    public function acceptDocument(Request $request, string $slug): RedirectResponse
    {
        abort_unless($request->user(), 401);
        $document = InstitutionalDocument::query()->where('slug', $slug)->where('status', 'published')->firstOrFail();
        abort_unless($document->requires_acceptance, 409);
        $version = $document->versions()->where('status', 'published')->latest('version')->firstOrFail();

        DB::table('institutional_document_acceptances')->insertOrIgnore([
            'institutional_document_version_id' => $version->id,
            'user_id' => $request->user()->id,
            'accepted_at' => now(),
            'source' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Votre acceptation de cette version a été enregistrée.');
    }
}
