<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\InstitutionalContent;
use App\Models\PublicPage;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContentManagementController extends Controller
{
    public function __construct(private readonly AuditLogService $audit)
    {
    }

    public function pages(): View
    {
        return view('admin.content.pages', ['pages' => PublicPage::query()->latest('updated_at')->paginate(25)]);
    }

    public function storePage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'alpha_dash', 'max:180', 'unique:public_pages,slug'],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:50000'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $page = PublicPage::create($data + ['status' => 'draft', 'created_by' => $request->user()->getAuthIdentifier(), 'updated_by' => $request->user()->getAuthIdentifier()]);
            $page->versions()->create(['version' => 1, 'snapshot' => $page->only(['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'status']), 'created_by' => $request->user()->getAuthIdentifier(), 'created_at' => now()]);
            $this->audit->record('public_page_created', $page, $request->user(), ['slug' => $page->slug], $request);
        });

        return back()->with('success', 'Page créée en brouillon.');
    }

    public function updatePage(Request $request, PublicPage $page): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:50000'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);

        DB::transaction(function () use ($data, $page, $request): void {
            $page->fill($data);
            $page->updated_by = $request->user()->getAuthIdentifier();
            $page->save();
            $version = (int) $page->versions()->max('version') + 1;
            $page->versions()->create(['version' => $version, 'snapshot' => $page->only(['title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'status']), 'created_by' => $request->user()->getAuthIdentifier(), 'created_at' => now()]);
            $this->audit->record('public_page_updated', $page, $request->user(), ['version' => $version], $request);
        });

        return back()->with('success', 'Page modifiée et nouvelle version conservée.');
    }

    public function publishPage(Request $request, PublicPage $page): RedirectResponse
    {
        $page->update(['status' => 'published', 'published_at' => now(), 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit->record('public_page_published', $page, $request->user(), ['slug' => $page->slug], $request);

        return back()->with('success', 'Page publiée.');
    }

    public function faq(): View
    {
        return view('admin.content.faq', [
            'categories' => FaqCategory::query()->withCount('items')->orderBy('sort_order')->get(),
            'items' => FaqItem::query()->with('category')->orderBy('faq_category_id')->orderBy('sort_order')->paginate(30),
        ]);
    }

    public function storeFaqCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'slug' => ['required', 'alpha_dash', 'max:140', 'unique:faq_categories,slug'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000']]);
        FaqCategory::create($data + ['is_active' => true]);
        $this->audit->record('faq_category_created', null, $request->user(), ['slug' => $data['slug']], $request);

        return back()->with('success', 'Catégorie FAQ créée.');
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'faq_category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:240'],
            'answer' => ['required', 'string', 'max:12000'],
            'audience' => ['required', 'in:all,client,professional'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $item = FaqItem::create($data + ['is_active' => false, 'created_by' => $request->user()->getAuthIdentifier()]);
        $this->audit->record('faq_item_created', $item, $request->user(), ['audience' => $item->audience], $request);

        return back()->with('success', 'Question ajoutée en brouillon. Activez-la après vérification.');
    }

    public function updateFaq(Request $request, FaqItem $item): RedirectResponse
    {
        $data = $request->validate([
            'faq_category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:240'],
            'answer' => ['required', 'string', 'max:12000'],
            'audience' => ['required', 'in:all,client,professional'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
        $item->update($data);
        $this->audit->record('faq_item_updated', $item, $request->user(), ['audience' => $item->audience], $request);

        return back()->with('success', 'Question FAQ mise à jour.');
    }

    public function toggleFaq(Request $request, FaqItem $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);
        $this->audit->record('faq_item_visibility_changed', $item, $request->user(), ['is_active' => $item->is_active], $request);

        return back()->with('success', $item->is_active ? 'Question publiée.' : 'Question désactivée.');
    }

    public function institutional(): View
    {
        return view('admin.content.institutional', ['contents' => InstitutionalContent::query()->latest('updated_at')->paginate(25)]);
    }

    public function storeInstitutional(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'alpha_dash', 'max:120', 'unique:institutional_contents,key'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:50000'],
            'effective_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $content = InstitutionalContent::create($data + ['status' => 'draft', 'version' => 1, 'created_by' => $request->user()->getAuthIdentifier(), 'updated_by' => $request->user()->getAuthIdentifier()]);
            $content->versions()->create(['version' => 1, 'snapshot' => $content->only(['key', 'title', 'body', 'status', 'effective_at']), 'created_by' => $request->user()->getAuthIdentifier(), 'created_at' => now()]);
            $this->audit->record('institutional_content_created', $content, $request->user(), ['key' => $content->key], $request);
        });

        return back()->with('success', 'Contenu institutionnel créé en brouillon.');
    }

    public function updateInstitutional(Request $request, InstitutionalContent $content): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'body' => ['required', 'string', 'max:50000'], 'effective_at' => ['nullable', 'date']]);

        DB::transaction(function () use ($data, $content, $request): void {
            $content->fill($data);
            $content->version = $content->version + 1;
            $content->updated_by = $request->user()->getAuthIdentifier();
            $content->save();
            $content->versions()->create(['version' => $content->version, 'snapshot' => $content->only(['key', 'title', 'body', 'status', 'effective_at']), 'created_by' => $request->user()->getAuthIdentifier(), 'created_at' => now()]);
            $this->audit->record('institutional_content_updated', $content, $request->user(), ['version' => $content->version], $request);
        });

        return back()->with('success', 'Contenu modifié et version précédente conservée.');
    }

    public function publishInstitutional(Request $request, InstitutionalContent $content): RedirectResponse
    {
        $content->update(['status' => 'published', 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit->record('institutional_content_published', $content, $request->user(), ['version' => $content->version], $request);

        return back()->with('success', 'Contenu institutionnel publié.');
    }
}
