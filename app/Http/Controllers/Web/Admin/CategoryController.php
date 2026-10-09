<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\AdminCategoryIndexRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
    ) {}

    public function index(AdminCategoryIndexRequest $request): View
    {
        $data = $request->validated();

        $categories = Category::query()
            ->with('parent')
            ->withCount(['children', 'services'])
            ->when(
                $data['search'] ?? null,
                fn ($query, $value) => $query->where(
                    fn ($nested) => $nested
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('slug', 'like', "%{$value}%"),
                ),
            )
            ->when($data['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when(isset($data['parent_id']), fn ($query) => $query->where('parent_id', $data['parent_id']))
            ->orderByRaw(match ($data['sort'] ?? '-sort_order') {
                'name' => 'name ASC',
                '-name' => 'name DESC',
                'sort_order' => 'sort_order ASC',
                'created_at' => 'created_at ASC',
                '-created_at' => 'created_at DESC',
                default => 'sort_order DESC',
            })
            ->paginate($data['per_page'] ?? 20)
            ->withQueryString();

        return view('admin.categories.index', [
            'categories' => $categories,
            'filters' => $data,
            'parents' => Category::root()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', [
            'parents' => Category::root()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $this->categoryService->create($request->validated());

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été créée.');
    }

    public function show(Category $category): View
    {
        $this->authorize('view', $category);

        return view('admin.categories.show', [
            'category' => $category->load(['parent', 'children'])->loadCount('services'),
        ]);
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.edit', [
            'category' => $category,
            'parents' => Category::root()
                ->active()
                ->where('id', '!=', $category->getKey())
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->categoryService->update($category, $request->validated());

        return redirect()->route('admin.categories.show', $category)->with('success', 'La catégorie a été mise à jour.');
    }

    public function archive(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);
        $this->categoryService->archive($category);

        return redirect()->route('admin.categories.index')->with('success', 'La catégorie a été archivée.');
    }
}
