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
            $parentId = $attributes['parent_id'] ?? null;

            $this->assertParentIsValid($parentId);
            $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $attributes['name']);
            $this->assertSiblingNameIsUnique($attributes['name'], $parentId);

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

            $newStatus = array_key_exists('status', $attributes)
                ? ($attributes['status'] instanceof CategoryStatus
                    ? $attributes['status']
                    : CategoryStatus::from($attributes['status']))
                : $category->status;

            if ($newStatus !== CategoryStatus::ACTIVE && $category->children()
                ->where('status', CategoryStatus::ACTIVE->value)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Une catégorie avec des sous-catégories actives ne peut pas être désactivée ou archivée.',
                ]);
            }

            if ($newStatus === CategoryStatus::ACTIVE && $parentId !== null) {
                $parent = Category::query()->findOrFail($parentId);

                if ($parent->status !== CategoryStatus::ACTIVE) {
                    throw ValidationException::withMessages([
                        'status' => 'Une sous-catégorie ne peut pas être active lorsque sa catégorie parente est inactive ou archivée.',
                    ]);
                }
            }

            $category->update($attributes);

            return $category->refresh();
        });
    }

    public function archive(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            if ($category->children()->where('status', CategoryStatus::ACTIVE->value)->exists()) {
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

        if ($parent->parent_id !== null) {
            throw ValidationException::withMessages([
                'parent_id' => 'La hiérarchie des catégories est limitée à deux niveaux.',
            ]);
        }

        if ($category !== null && $category->parent_id === null && $category->children()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Une catégorie ayant des sous-catégories ne peut pas devenir une sous-catégorie.',
            ]);
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
