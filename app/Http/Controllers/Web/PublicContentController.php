<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\FaqCategory;
use App\Models\InstitutionalContent;
use App\Models\PublicPage;
use Illuminate\View\View;

class PublicContentController extends Controller
{
    public function page(string $slug): View
    {
        $page = PublicPage::query()->published()->where('slug', $slug)->firstOrFail();

        return view('public.content.page', compact('page'));
    }

    public function faq(): View
    {
        $audience = auth()->check()
            ? (auth()->user()->hasRole('professional') ? 'professional' : 'client')
            : null;

        $categories = FaqCategory::query()
            ->where('is_active', true)
            ->with(['items' => fn ($query) => $query
                ->where('is_active', true)
                ->when($audience, fn ($q) => $q->whereIn('audience', ['all', $audience]))
                ->when(! $audience, fn ($q) => $q->where('audience', 'all'))
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($category) => $category->items->isNotEmpty());

        return view('public.content.faq', compact('categories'));
    }

    public function announcements(): View
    {
        $audience = auth()->check()
            ? (auth()->user()->hasRole('professional') ? 'professional' : 'client')
            : null;

        $announcements = Announcement::query()
            ->visible($audience)
            ->when(! $audience, fn ($query) => $query->where('audience', 'all'))
            ->paginate(15);

        return view('public.content.announcements', compact('announcements'));
    }

    public function institutional(string $key): View
    {
        $content = InstitutionalContent::query()
            ->where('key', $key)
            ->where('status', 'published')
            ->where(fn ($q) => $q->whereNull('effective_at')->orWhere('effective_at', '<=', now()))
            ->firstOrFail();

        return view('public.content.institutional', compact('content'));
    }
}