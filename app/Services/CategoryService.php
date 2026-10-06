<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $attributes['name']);
            $this->assertParentIsValid($attributes['parent_id'] ?? null);
            $this->assertSiblingNameIsUnique($attributes['name'], $attributes['parent_id'] ?? null);

            return Category::create($attributes);
        });
    }

    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $parentId = array_key_exists('parent_id', $attributes)
                ? $attributes['parent_id']
                : $category->parent_id;

            $name = $attributes['name'] ?? $category->name;

            $this->assertParentIsValid($parentId, $category);
            $this->assertSiblingNameIsUnique($name, $parentId, $category);

            if (array_key_exists('status', $attributes)) {
                $status = $attributes['status'] instanceof CategoryStatus
                    ? $attributes['status']
                    : CategoryStatus::from($attributes['status']);

                if ($status === CategoryStatus::ACTIVE && $category->parent_id !== null) {
                    $parent = Category::query()->findOrFail($parentId);
                    if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'Une catégorie ne peut avoir qu’un seul niveau de sous-catégorie.',
            ]);
        }

        if ($parent->status !== CategoryStatus::ACTIVE) {
                        throw ValidationException::withMessages([
                            'status' => 'Une sous-catégorie ne peut pas être active lorsque sa catégorie parente est inactive ou archivée.',
                        ]);
                    }
                }
            }

            $category->update($attributes);

            return $category->refresh();
        });
    }

    public function archive(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $hasActiveChildren = $category->children()
                ->where('status', CategoryStatus::ACTIVE->value)
                ->exists();

            if ($hasActiveChildren) {
                throw ValidationException::withMessages([
                    'category' => 'Archivez ou réaffectez d’abord les sous-catégories actives.',
                ]);
            }

            $category->update([
                'status' => CategoryStatus::ARCHIVED,
                'is_featured' => false,
            ]);
        });
    }

    private function resolveSlug(?string $slug, string $name): string
    {
        $base = $slug !== null && $slug !== '' ? $slug : Str::slug($name);
        $candidate = $base;
        $suffix = 2;

        while (Category::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function assertParentIsValid(?int $parentId, ?Category $category = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && $parentId === $category->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Une catégorie ne peut pas être son propre parent.',
            ]);
        }

        $parent = Category::query()->find($parentId);
        if ($parent === null) {
            throw (new ModelNotFoundException)->setModel(Category::class, [$parentId]);
        }

        if ($parent->status !== CategoryStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'parent_id' => 'La catégorie parente doit être active.',
            ]);
        }

        if ($category !== null) {
            if ($category->parent_id === null && $category->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une catégorie ayant des sous-catégories ne peut pas devenir une sous-catégorie.',
                ]);
            }

            $cursor = $parent;
            while ($cursor->parent_id !== null) {
                if ($cursor->parent_id === $category->getKey()) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Cette opération créerait une boucle dans la hiérarchie des catégories.',
                    ]);
                }
                $cursor = $cursor->parent()->firstOrFail();
            }
        }
    }

    private function assertSiblingNameIsUnique(string $name, ?int $parentId, ?Category $ignore = null): void
    {
        $exists = Category::query()
            ->where('name', $name)
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->when($ignore !== null, fn ($query) => $query->where('id', '!=', $ignore->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Une catégorie portant ce nom existe déjà à ce niveau de la hiérarchie.',
            ]);
        }
    }
}
