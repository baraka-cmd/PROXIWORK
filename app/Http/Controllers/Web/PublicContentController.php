<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\CmsPage;
use App\Models\FaqArticle;
use App\Models\InstitutionalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function announcements(): View
    {
        return view('public.content.announcements', [
            'announcements' => Announcement::query()->visible()->orderByDesc('is_pinned')->orderByDesc('published_at')->paginate(15),
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
        $articles = FaqArticle::query()->published()
            ->with('category')
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('question', 'like', '%'.$search.'%')
                ->orWhere('answer', 'like', '%'.$search.'%')))
            ->orderBy('sort_order')->paginate(20)->withQueryString();

        return view('public.content.faq', ['articles' => $articles, 'search' => $search]);
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

        DB::table('institutional_document_acceptances')->updateOrInsert(
            ['institutional_document_version_id' => $version->id, 'user_id' => $request->user()->id],
            ['accepted_at' => now(), 'source' => 'web', 'created_at' => now(), 'updated_at' => now()],
        );

        return back()->with('success', 'Votre acceptation de cette version a été enregistrée.');
    }
}
