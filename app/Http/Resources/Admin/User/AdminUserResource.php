<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'account_status' => $this->account_status?->value,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'suspended_at' => $this->suspended_at?->toISOString(),
            'suspended_until' => $this->suspended_until?->toISOString(),
            'suspension_reason' => $this->suspension_reason,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
