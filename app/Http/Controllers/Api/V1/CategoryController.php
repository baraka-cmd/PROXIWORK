<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Audit\AuditLogService;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with('parent:id,name,slug')
            ->withCount('children')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->input('search'));
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->has('parent_id'), function ($query) use ($request): void {
                $parentId = $request->input('parent_id');
                $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);
            })
            ->when($request->boolean('root_only'), fn ($query) => $query->whereNull('parent_id'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return CategoryResource::collection($categories)->additional([
            'message' => 'Catégories récupérées avec succès.',
            'meta' => [],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());
        $this->auditLogService->record('category_created', $category, $request->user(), [], $request);

        return (new CategoryResource($category->load('parent:id,name,slug')))->additional([
            'message' => 'Catégorie créée avec succès.',
            'meta' => [],
        ])->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        $category->load('parent:id,name,slug')->loadCount('children');

        return (new CategoryResource($category))->additional([
            'message' => 'Catégorie récupérée avec succès.',
            'meta' => [],
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->authorize('update', $category);

        $category = $this->categoryService->update($category, $request->validated());
        $this->auditLogService->record('category_updated', $category, $request->user(), [], $request);

        return (new CategoryResource($category->load('parent:id,name,slug')))->additional([
            'message' => 'Catégorie mise à jour avec succès.',
            'meta' => [],
        ]);
    }

    public function destroy(Request $request, Category $category): Response
    {
        $this->authorize('delete', $category);

        $this->categoryService->archive($category);
        $this->auditLogService->record('category_archived', $category, $request->user(), [], $request);

        return response()->noContent();
    }
}
