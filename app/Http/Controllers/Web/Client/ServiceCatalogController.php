<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\CategoryStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
        ]);

        $services = Service::query()
            ->published()
            ->with([
                'category',
                'skills',
                'images',
                'professionalProfile.user.profile',
            ])
            ->whereHas('category', function ($query): void {
                $query->where('status', CategoryStatus::ACTIVE->value);
            })
            ->whereHas('professionalProfile', function ($query): void {
                $query
                    ->where('status', 'active')
                    ->where('visibility', 'public');
            })
            ->when(
                $filters['search'] ?? null,
                function ($query, string $search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere(
                                'short_description',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $filters['category'] ?? null,
                fn ($query, int $categoryId) => $query->where('category_id', $categoryId)
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('client.services.index', [
            'services' => $services,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }
}
