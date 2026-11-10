<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Analytics;

class AdminAnalyticsExportRequest extends AdminAnalyticsIndexRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('analytics.export') ?? false;
    }
}
