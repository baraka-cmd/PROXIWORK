<?php

declare(strict_types=1);

namespace App\Http\Resources\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryDetailResource extends JsonResource
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
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ]),
            'children' => $this->whenLoaded('children', fn () => self::collection($this->children)),
            'children_count' => $this->when(isset($this->children_count), fn () => (int) $this->children_count),
        ];
    }
}
