<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\AdminCategoryIndexRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\Audit\AuditLogService;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(AdminCategoryIndexRequest $request): View
    {
        $data = $request->validated();

        $categories = Category::query()
            ->with('parent')
            ->withCount(['children', 'services', 'skills'])
            ->when($data['search'] ?? null, fn ($query, $value) => $query->where(
                fn ($nested) => $nested->where('name', 'like', '%'.$value.'%')
                    ->orWhere('slug', 'like', '%'.$value.'%')
            ))
            ->when($data['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when(array_key_exists('parent_id', $data) && $data['parent_id'] !== null,
                fn ($query) => $query->where('parent_id', $data['parent_id']))
            ->orderByRaw(match ($data['sort'] ?? '-sort_order') {
                'name' => 'name ASC',
                '-name' => 'name DESC',
                'sort_order' => 'sort_order ASC',
                'created_at' => 'created_at ASC',
                '-created_at' => 'created_at DESC',
                default => 'sort_order DESC',
            })
            ->orderBy('name')
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'filters' => $data,
            'parents' => Category::root()->orderBy('name')->get(['id', 'name']),
            'summary' => [
                'active' => Category::query()->where('status', 'active')->count(),
                'inactive' => Category::query()->where('status', 'inactive')->count(),
                'featured' => Category::query()->where('status', 'active')->where('is_featured', true)->count(),
                'subcategories' => Category::query()->whereNotNull('parent_id')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', [
            'parents' => Category::root()->active()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = $this->categoryService->create($request->validated());
        $this->auditLogService->record('category_created', $category, $request->user(), [], $request);

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été créée.');
    }

    public function show(Category $category): View
    {
        $this->authorize('view', $category);

        $category->load([
            'parent',
            'children' => fn ($query) => $query->withCount(['services', 'skills']),
        ])->loadCount(['services', 'skills']);

        $childIds = $category->children->modelKeys();
        $childServiceCount = $childIds === []
            ? 0
            : \App\Models\Service::query()->whereIn('category_id', $childIds)->count();
        $childSkillCount = $childIds === []
            ? 0
            : \App\Models\Skill::query()->whereIn('category_id', $childIds)->count();

        return view('admin.categories.show', [
            'category' => $category,
            'childServiceCount' => $childServiceCount,
            'childSkillCount' => $childSkillCount,
        ]);
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.edit', [
            'category' => $category,
            'parents' => Category::root()->active()
                ->where('id', '!=', $category->getKey())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category = $this->categoryService->update($category, $request->validated());
        $this->auditLogService->record('category_updated', $category, $request->user(), [
            'status' => $category->status->value,
            'parent_id' => $category->parent_id,
            'is_featured' => $category->is_featured,
        ], $request);

        return redirect()->route('admin.categories.show', $category)->with('success', 'La catégorie a été mise à jour.');
    }

    public function archive(\Illuminate\Http\Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->archive($category);
        $this->auditLogService->record('category_archived', $category, $request->user(), [], $request);

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été archivée.');
    }
}
