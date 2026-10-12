<?php

declare(strict_types=1);

namespace App\Http\Resources\Client\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'profile' => $this->resource['profile'],
            'addresses' => $this->resource['addresses'],
            'favorites' => $this->resource['favorites'],
            'notifications' => $this->resource['notifications'],
            'stats' => $this->resource['stats'],
            'chart' => $this->resource['chart'],
            'recent_activity' => $this->resource['recent_activity'],
            'pending_actions' => $this->resource['pending_actions'],
        ];
    }
}
