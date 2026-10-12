<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Client;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'pricing' => ['nullable', Rule::enum(ServicePricingType::class)],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('client.services.index')
                ->withErrors($validator);
        }

        $filters = $validator->validated();

        $services = $this->visibleServicesQuery()
            ->with([
                'category',
                'skills',
                'images',
                'professionalProfile.user.profile',
            ])
            ->when(
                $filters['search'] ?? null,
                function (Builder $query, string $search): void {
                    $query->where(function (Builder $query) use ($search): void {
                        $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('short_description', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $filters['category'] ?? null,
                fn (Builder $query, int $categoryId) => $query->where('category_id', $categoryId)
            )
            ->when(
                $filters['pricing'] ?? null,
                fn (Builder $query, string $pricing) => $query->where('pricing_type', $pricing)
            )
            ->when(
                $filters['city'] ?? null,
                function (Builder $query, string $city): void {
                    $query->whereHas(
                        'professionalProfile',
                        fn (Builder $profileQuery) => $profileQuery->where('city', 'like', "%{$city}%")
                    );
                }
            )
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $categories = Category::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $clientId = $request->user()->getKey();

        $stats = [
            'services' => $this->visibleServicesQuery()->count(),
            'categories' => Category::query()->active()->count(),
            'professionals' => ProfessionalProfile::query()
                ->where('status', 'active')
                ->where('visibility', 'public')
                ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                ->whereHas('user', fn (Builder $query) => $query->where('account_status', 'active'))
                ->whereHas('services', function (Builder $query): void {
                    $query
                        ->published()
                        ->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where(
                            'status',
                            CategoryStatus::ACTIVE->value
                        ));
                })
                ->count(),
            'requests' => ServiceRequest::query()->where('client_id', $clientId)->count(),
        ];

        $requestStatusCounts = ServiceRequest::query()
            ->where('client_id', $clientId)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn ($count): int => (int) $count)
            ->all();

        $requestStatuses = [
            [
                'value' => null,
                'label' => 'Toutes',
                'count' => $stats['requests'],
            ],
            [
                'value' => ServiceRequestStatus::DRAFT->value,
                'label' => 'Brouillons',
                'count' => $requestStatusCounts[ServiceRequestStatus::DRAFT->value] ?? 0,
            ],
            [
                'value' => ServiceRequestStatus::REQUESTED->value,
                'label' => 'Envoyées',
                'count' => $requestStatusCounts[ServiceRequestStatus::REQUESTED->value] ?? 0,
            ],
            [
                'value' => ServiceRequestStatus::QUOTED->value,
                'label' => 'Devis reçus',
                'count' => $requestStatusCounts[ServiceRequestStatus::QUOTED->value] ?? 0,
            ],
            [
                'value' => ServiceRequestStatus::ACCEPTED->value,
                'label' => 'Acceptées',
                'count' => $requestStatusCounts[ServiceRequestStatus::ACCEPTED->value] ?? 0,
            ],
            [
                'value' => ServiceRequestStatus::REJECTED->value,
                'label' => 'Refusées',
                'count' => $requestStatusCounts[ServiceRequestStatus::REJECTED->value] ?? 0,
            ],
            [
                'value' => ServiceRequestStatus::CANCELLED->value,
                'label' => 'Annulées',
                'count' => $requestStatusCounts[ServiceRequestStatus::CANCELLED->value] ?? 0,
            ],
        ];

        return view('client.services.index', [
            'services' => $services,
            'categories' => $categories,
            'filters' => $filters,
            'stats' => $stats,
            'requestStatuses' => $requestStatuses,
        ]);
    }

    /**
     * Only services safe to discover from the authenticated client catalogue.
     *
     * @return Builder<Service>
     */
    private function visibleServicesQuery(): Builder
    {
        return Service::query()
            ->published()
            ->whereHas('category', function (Builder $query): void {
                $query->where('status', CategoryStatus::ACTIVE->value);
            })
            ->whereHas('professionalProfile', function (Builder $query): void {
                $query
                    ->where('status', 'active')
                    ->where('visibility', 'public')
                    ->where('verification_status', ProfessionalVerificationStatus::VERIFIED->value)
                    ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('account_status', 'active'));
            });
    }
}
