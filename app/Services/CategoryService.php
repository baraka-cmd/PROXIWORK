<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CategoryStatus;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    public function create(array $attributes): Category
    {
        return DB::transaction(function () use ($attributes): Category {
            $parentId = isset($attributes['parent_id']) ? (int) $attributes['parent_id'] : null;
            $this->assertParentIsValid($parentId);
            $attributes['parent_id'] = $parentId;
            $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $attributes['name']);
            $this->assertSiblingNameIsUnique($attributes['name'], $parentId);

            if ($parentId !== null && (bool) ($attributes['is_featured'] ?? false)) {
                throw ValidationException::withMessages([
                    'is_featured' => 'Seules les catégories principales peuvent être mises en avant.',
                ]);
            }

            return Category::query()->create($attributes);
        });
    }

    public function update(Category $category, array $attributes): Category
    {
        return DB::transaction(function () use ($category, $attributes): Category {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());
            $parentId = array_key_exists('parent_id', $attributes)
                ? ($attributes['parent_id'] === null ? null : (int) $attributes['parent_id'])
                : $locked->parent_id;
            $name = $attributes['name'] ?? $locked->name;

            $this->assertParentIsValid($parentId, $locked);
            $this->assertSiblingNameIsUnique($name, $parentId, $locked);

            $newStatus = array_key_exists('status', $attributes)
                ? ($attributes['status'] instanceof CategoryStatus
                    ? $attributes['status']
                    : CategoryStatus::from($attributes['status']))
                : $locked->status;

            if ($newStatus !== CategoryStatus::ACTIVE && $locked->children()
                ->where('status', CategoryStatus::ACTIVE->value)->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Désactivez, archivez ou réaffectez d’abord les sous-catégories actives.',
                ]);
            }

            if ($newStatus === CategoryStatus::ACTIVE && $parentId !== null) {
                $this->assertParentIsValid($parentId, $locked);
            }

            if ($parentId !== null && (bool) ($attributes['is_featured'] ?? $locked->is_featured)) {
                throw ValidationException::withMessages([
                    'is_featured' => 'Seules les catégories principales peuvent être mises en avant.',
                ]);
            }

            if (array_key_exists('name', $attributes) || array_key_exists('slug', $attributes)) {
                $attributes['slug'] = $this->resolveSlug($attributes['slug'] ?? null, $name, $locked);
            }

            if ($newStatus !== CategoryStatus::ACTIVE) {
                $attributes['is_featured'] = false;
            }

            $attributes['parent_id'] = $parentId;
            $locked->update($attributes);

            return $locked->refresh()->load(['parent', 'children']);
        });
    }

    public function archive(Category $category): void
    {
        DB::transaction(function () use ($category): void {
            $locked = Category::query()->lockForUpdate()->findOrFail($category->getKey());

            if ($locked->children()->where('status', CategoryStatus::ACTIVE->value)->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Archivez ou réaffectez d’abord les sous-catégories actives.',
                ]);
            }

            $locked->update([
                'status' => CategoryStatus::ARCHIVED,
                'is_featured' => false,
            ]);
        });
    }

    private function resolveSlug(?string $slug, string $name, ?Category $ignore = null): string
    {
        $base = Str::slug(filled($slug) ? $slug : $name) ?: 'categorie';
        $candidate = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $candidate)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }

    private function assertParentIsValid(?int $parentId, ?Category $category = null): void
    {
        if ($parentId === null) {
            return;
        }

        if ($category !== null && $parentId === (int) $category->getKey()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Une catégorie ne peut pas être son propre parent.',
            ]);
        }

        $parent = Category::query()->lockForUpdate()->findOrFail($parentId);

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
                'parent_id' => 'Une catégorie principale qui possède des sous-catégories ne peut pas devenir une sous-catégorie.',
            ]);
        }
    }

    private function assertSiblingNameIsUnique(string $name, ?int $parentId, ?Category $ignore = null): void
    {
        $exists = Category::query()
            ->where('name', $name)
            ->when($parentId === null, fn ($query) => $query->whereNull('parent_id'))
            ->when($parentId !== null, fn ($query) => $query->where('parent_id', $parentId))
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Une catégorie portant ce nom existe déjà à ce niveau de la hiérarchie.',
            ]);
        }
    }
}
