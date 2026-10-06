<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\CategoryStatus;
use App\Http\Requests\Category\IndexCategoryRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\Category\CategoryDetailResource;
use App\Http\Resources\Category\CategoryListResource;
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
    private readonly CategoryService $categoryService;

    private readonly AuditLogService $auditLogService;

    public function __construct(
        CategoryService $categoryService,
        AuditLogService $auditLogService,
    ) {
        $this->categoryService = $categoryService;
        $this->auditLogService = $auditLogService;
    }

    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $categories = Category::query()
            ->with('parent:id,name,slug')
            ->withCount('children')
            ->where('status', CategoryStatus::ACTIVE->value)
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists('parent_id', $validated), function ($query) use ($validated): void {
                $validated['parent_id'] === null
                    ? $query->whereNull('parent_id')
                    : $query->where('parent_id', $validated['parent_id']);
            })
            ->when(($validated['root_only'] ?? false) === true, fn ($query) => $query->whereNull('parent_id'))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return CategoryListResource::collection($categories)->additional([
            'message' => 'Catégories récupérées avec succès.',
            'meta' => ['scope' => 'active'],
        ]);
    }

    public function adminIndex(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Category::class);
        $validated = $request->validated();

        $categories = Category::query()
            ->with('parent:id,name,slug')
            ->withCount('children')
            ->when(array_key_exists('status', $validated), fn ($query) => $query->where('status', CategoryStatus::from($validated['status'])->value))
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return CategoryResource::collection($categories)->additional([
            'message' => 'Catégories administratives récupérées avec succès.',
            'meta' => ['scope' => 'admin'],
        ]);
    }

    public function adminShow(Category $category): CategoryResource
    {
        $this->authorize('view', $category);

        return (new CategoryResource($category->load('parent:id,name,slug')->loadCount('children')))->additional([
            'message' => 'Catégorie administrative récupérée avec succès.',
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

    public function show(Category $category): CategoryDetailResource
    {
        abort_if($category->status !== CategoryStatus::ACTIVE, 404);

        $category->load([
            'parent:id,name,slug',
            'children' => fn ($query) => $query
                ->where('status', CategoryStatus::ACTIVE->value)
                ->orderBy('sort_order')
                ->orderBy('name'),
        ])->loadCount('children');

        return (new CategoryDetailResource($category))->additional([
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
