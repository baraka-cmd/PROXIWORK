<?php

declare(strict_types=1);

namespace App\Http\Resources\Category;

use App\Http\Resources\CategoryResource;
use Illuminate\Http\Request;

class CategoryListResource extends CategoryResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'image_path' => $this->image_path,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => (int) $this->sort_order,
            'children_count' => (int) ($this->children_count ?? 0),
        ];
    }
}
