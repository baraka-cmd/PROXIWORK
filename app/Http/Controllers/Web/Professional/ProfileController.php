<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Http\Requests\ProfessionalService\StoreServiceImageRequest;
use App\Http\Requests\ProfessionalService\StoreServiceRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceImageRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceRequest;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\Skill;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ProfessionalServiceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfessionalServiceManager $serviceManager,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function show(Request $request): View
    {

        $professional = $request->user()->professionalProfile()
            ->with([
                'user.profile',
                'skills',
                'services' => fn ($query) => $query
                    ->with(['category', 'skills', 'images'])
                    ->orderBy('sort_order')
                    ->orderByDesc('id'),
            ])
            ->withCount([
                'skills',
                'services',
                'services as published_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::PUBLISHED->value)
                    ->whereNotNull('published_at'),
            ])
            ->firstOrFail();

        // Limiter l'affichage aux six premiers services après leur chargement.
        // Cela évite la requête ROW_NUMBER() générée par Laravel.
        $professional->setRelation(
            'services',
            $professional->services->take(6)->values()
        );

        $completionItems = [
            'personal' => $professional->user->profile !== null,
            'profession' => filled($professional->professional_title),
            'presentation' => filled($professional->description),
            'service_area' => filled($professional->city) && filled($professional->province),
            'skills' => $professional->skills_count > 0,
            'services' => $professional->services_count > 0,
            'verification' => $professional->verification_status === ProfessionalVerificationStatus::VERIFIED,
        ];

        return view('professional/profile', [
            'professional' => $professional,
            'completionItems' => $completionItems,
            'completionPercentage' => (int) round((count(array_filter($completionItems)) / count($completionItems)) * 100),
            'availabilityLabels' => [
                ProfessionalAvailabilityStatus::UNKNOWN->value => 'Non renseignée',
                ProfessionalAvailabilityStatus::AVAILABLE->value => 'Disponible',
                ProfessionalAvailabilityStatus::UNAVAILABLE->value => 'Indisponible',
            ],
        ]);
    }

    public function edit(Request $request): View
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();

        return view('professional/profile-edit', [
            'professional' => $professional,
        ]);
    }

    public function update(UpdateProfessionalProfileRequest $request): RedirectResponse
    {
        $professional = $request->user()->professionalProfile()->firstOrFail();
        $professional->fill($request->validated())->save();

        $this->auditLogService->record(
            'professional_profile_updated',
            $professional,
            $request->user(),
            [],
            $request,
        );

        return redirect()->route('professional.profile')
            ->with('success', 'Votre profil professionnel a été mis à jour.');
    }

    public function services(Request $request): View
    {
        $this->authorize('viewAny', Service::class);

        $profile = $request->user()->professionalProfile()->firstOrFail();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ServiceStatus::class)],
        ]);

        $services = $profile->services()
            ->with(['category', 'skills', 'images'])
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('professional/services/index', [
            'services' => $services,
            'filters' => $validated,
            'statuses' => ServiceStatus::cases(),
        ]);
    }

    public function createService(Request $request): View
    {
        $this->authorize('create', Service::class);

        return view('professional/services/form', [
            'service' => null,
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
            'skills' => Skill::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function storeService(StoreServiceRequest $request): RedirectResponse
    {
        $profile = $request->user()->professionalProfile()->firstOrFail();
        $service = $this->serviceManager->create($profile, $request->validated());

        return redirect()->route('professional.services.edit', $service)
            ->with('success', 'Service créé. Ajoutez maintenant sa couverture avant publication.');
    }

    public function editService(Request $request, Service $service): View
    {
        $this->authorize('view', $service);

        return view('professional/services/form', [
            'service' => $service->load(['category', 'skills', 'images']),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(),
            'skills' => Skill::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function updateService(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $this->serviceManager->update($service, $request->validated());

        return redirect()->route('professional.services.edit', $service)
            ->with('success', 'Service mis à jour avec succès.');
    }

    public function archiveService(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);
        $this->serviceManager->archive($service);

        return redirect()->route('professional.services.index')
            ->with('success', 'Service archivé avec succès.');
    }

    public function publishService(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $this->serviceManager->publish($service);

        return redirect()->route('professional.services.index')
            ->with('success', 'Service publié avec succès.');
    }

    public function unpublishService(Request $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $this->serviceManager->unpublish($service);

        return redirect()->route('professional.services.index')
            ->with('success', 'Service dépublié avec succès.');
    }

    public function addImage(StoreServiceImageRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('update', $service);
        $this->serviceManager->addImage($service, $request->file('image'), $request->validated());

        return back()->with('success', 'Image ajoutée avec succès.');
    }

    public function updateImage(UpdateServiceImageRequest $request, ServiceImage $image): RedirectResponse
    {
        $this->serviceManager->updateImage($image, $request->validated());

        return back()->with('success', 'Image mise à jour avec succès.');
    }

    public function deleteImage(UpdateServiceImageRequest $request, ServiceImage $image): RedirectResponse
    {
        $this->serviceManager->deleteImage($image);

        return back()->with('success', 'Image supprimée avec succès.');
    }
}
