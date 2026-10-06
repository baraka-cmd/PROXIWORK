<?php

declare(strict_types=1);

namespace App\Http\Resources\Professional\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'profile' => $this->resource['profile'],
            'services' => $this->resource['services'],
            'engagement' => $this->resource['engagement'],
            'notifications' => $this->resource['notifications'],
            'pending_actions' => $this->resource['pending_actions'],
            'future_modules' => $this->resource['future_modules'],
        ];
    }
}
