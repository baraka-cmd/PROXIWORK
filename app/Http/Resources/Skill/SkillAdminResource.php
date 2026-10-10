<?php

declare(strict_types=1);

namespace App\Http\Resources\Skill;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'status' => $this->status->value,
            'sort_order' => $this->sort_order,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
