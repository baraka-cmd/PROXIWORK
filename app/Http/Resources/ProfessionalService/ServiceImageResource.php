<?php

declare(strict_types=1);

namespace App\Http\Resources\ProfessionalService;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ServiceImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'url' => Storage::disk('public')->url($this->path),
            'alt_text' => $this->alt_text,
            'sort_order' => $this->sort_order,
            'is_cover' => $this->is_cover,
        ];
    }
}
