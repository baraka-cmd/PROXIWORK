<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ProfessionalAvailabilityStatus;
use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfessionalProfile\UpdateProfessionalProfileRequest;
use App\Http\Requests\ProfessionalService\ReplaceServiceImageRequest;
use App\Http\Requests\ProfessionalService\StoreServiceImageRequest;
use App\Http\Requests\ProfessionalService\StoreServiceRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceImageRequest;
use App\Http\Requests\ProfessionalService\UpdateServiceRequest;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\Skill;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\ProfessionalService\ProfessionalServiceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfessionalServiceManager $serviceManager,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function photo(Request $request): BinaryFileResponse
    {
        $path = $request->user()->profile()->value('avatar_path');

        abort_unless(
            is_string($path) && $path !== '' && Storage::disk('local')->exists($path),
            404,
        );

        $mimeType = Storage::disk('local')->mimeType($path);
        abort_unless(
            is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $response = response()->file(Storage::disk('local')->path($path));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    public function show(Request $request): View
    {
        $professional = $request->user()->professionalProfile()
            ->with(['user.profile', 'skills'])
            ->withCount([
                'skills',
                'services as published_services_count' => fn ($query) => $query
                    ->where('status', ServiceStatus::PUBLISHED->value)
                    ->whereNotNull('published_at')
                    ->whereHas('category', fn ($query) => $query->where('status', 'active')),
            ])
            ->firstOrFail();

        // Charger uniquement les six services affichés, sans hydrater tout le catalogue.
        $professional->setRelation(
            'services',
            $professional->services()
                ->with(['category', 'skills', 'images'])
                ->orderBy('sort_order')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        );

        $completionItems = [
            'personal' => filled($professional->user->profile?->first_name)
                && filled($professional->user->profile?->last_name),
            'profession' => filled($professional->professional_title),
            'presentation' => filled($professional->description),
            'service_area' => filled($professional->city) && filled($professional->province),
            'skills' => $professional->skills_count > 0,
        ];

        return view('professional/profile', [
            'professional' => $professional,
            'completionItems' => $completionItems,
            'completionPercentage' => (int) round(
                (count(array_filter($completionItems)) / count($completionItems)) * 100,
            ),
            'availabilityLabels' => [
                ProfessionalAvailabilityStatus::UNKNOWN->value => 'Non renseignée',
                ProfessionalAvailabilityStatus::AVAILABLE->value => 'Disponible',
                ProfessionalAvailabilityStatus::UNAVAILABLE->value => 'Indisponible',
            ],
            'visibilityLabels' => [
                'public' => 'Préférence publique',
                'private' => 'Privé',
            ],
            'profileStatusLabels' => [
                ProfessionalProfile::STATUS_DRAFT => 'Brouillon',
                ProfessionalProfile::STATUS_ACTIVE => 'Actif',
                ProfessionalProfile::STATUS_SUSPENDED => 'Suspendu',
                ProfessionalProfile::STATUS_CLOSED => 'Fermé',
            ],
            'verificationLabels' => [
                'pending' => 'En attente',
                'under_review' => 'En cours d’examen',
                'verified' => 'Vérifié',
                'rejected' => 'Refusé',
            ],
        ]);
    }

    public function edit(Request $request): View
    {
        $professional = $request->user()->professionalProfile()
            ->with(['user.profile', 'skills'])
            ->firstOrFail();

        $existingSkillIds = $professional->skills->modelKeys();
        $skills = Skill::query()
            ->where(function ($query) use ($existingSkillIds): void {
                $query->where('status', 'active')
                    ->orWhereIn('id', $existingSkillIds);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return view('professional/profile-edit', [
            'professional' => $professional,
            'skills' => $skills,
            'selectedSkillIds' => $existingSkillIds,
            'availabilityOptions' => [
                ProfessionalAvailabilityStatus::UNKNOWN->value => 'Non renseignée',
                ProfessionalAvailabilityStatus::AVAILABLE->value => 'Disponible pour de nouvelles demandes',
                ProfessionalAvailabilityStatus::UNAVAILABLE->value => 'Indisponible temporairement',
            ],
        ]);
    }

    public function update(UpdateProfessionalProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $identity = array_intersect_key($validated, array_flip(['first_name', 'last_name']));
        $availability = $validated['availability_status'];
        $visibility = $validated['visibility'];
        unset($validated['first_name'], $validated['last_name'], $validated['availability_status'], $validated['visibility'], $validated['languages_text']);

        $user = $request->user();

        DB::transaction(function () use ($user, $identity, $validated, $availability, $visibility): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $professional = $lockedUser->professionalProfile()->lockForUpdate()->firstOrFail();

            if ($identity !== []) {
                $profile = $lockedUser->profile()->firstOrCreate([]);
                $profile->fill($identity)->save();

                $firstName = trim((string) $profile->first_name);
                $lastName = trim((string) $profile->last_name);

                if ($firstName !== '' && $lastName !== '') {
                    $lockedUser->forceFill(['name' => $firstName.' '.$lastName])->save();
                }
            }

            $professional->fill($validated);
            $professional->forceFill([
                'availability_status' => $availability,
                'visibility' => $visibility,
            ])->save();
        });

        $professional = $user->professionalProfile()->firstOrFail();

        $this->auditLogService->record(
            'professional_profile_updated',
            $professional,
            $user,
            ['fields' => array_values(array_unique(array_merge(array_keys($identity), array_keys($validated), ['availability_status', 'visibility'])))],
            $request,
        );

        return redirect()->route('professional.profile')
            ->with('success', 'Votre profil professionnel a été mis à jour.');
    }

    public function storePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=2000,max_height=2000',
            ],
        ]);

        $user = $request->user();
        $newPath = $validated['avatar']->store('professional-avatars/'.$user->getKey(), 'local');

        if (! is_string($newPath) || $newPath === '') {
            return back()->withErrors([
                'avatar' => 'La photo n’a pas pu être enregistrée. Réessayez avec une autre image.',
            ]);
        }

        try {
            $oldPath = DB::transaction(function () use ($user, $newPath): ?string {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $profile = $lockedUser->profile()->firstOrCreate([]);
                $oldPath = $profile->avatar_path;
                $profile->forceFill(['avatar_path' => $newPath])->save();

                return is_string($oldPath) ? $oldPath : null;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->auditLogService->record(
            'professional_profile_photo_updated',
            $user->professionalProfile()->firstOrFail(),
            $user,
            [],
            $request,
        );

        return redirect()->route('professional.profile.edit')
            ->with('success', 'Photo de profil mise à jour.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldPath = DB::transaction(function () use ($user): ?string {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $profile = $lockedUser->profile()->first();

            if ($profile === null) {
                return null;
            }

            $oldPath = $profile->avatar_path;
            $profile->forceFill(['avatar_path' => null])->save();

            return is_string($oldPath) ? $oldPath : null;
        });

        if (is_string($oldPath) && $oldPath !== '') {
            Storage::disk('local')->delete($oldPath);
        }

        $this->auditLogService->record(
            'professional_profile_photo_removed',
            $user->professionalProfile()->firstOrFail(),
            $user,
            [],
            $request,
        );

        return redirect()->route('professional.profile.edit')
            ->with('success', 'Photo de profil supprimée.');
    }

    public function updateSkills(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'skill_ids' => ['sometimes', 'array', 'max:100'],
            'skill_ids.*' => ['integer', 'distinct', 'exists:skills,id'],
            'proficiency_levels' => ['sometimes', 'array', 'max:100'],
            'proficiency_levels.*' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced', 'expert'])],
            'skill_years_experience' => ['sometimes', 'array', 'max:100'],
            'skill_years_experience.*' => ['nullable', 'integer', 'min:0', 'max:80'],
        ]);

        $skillIds = array_map('intval', $validated['skill_ids'] ?? []);
        $professional = $request->user()->professionalProfile()->firstOrFail();
        $existing = $professional->skills()
            ->get()
            ->keyBy(fn ($skill) => (int) $skill->getKey());
        $existingIds = $existing->keys()->map(fn ($id) => (int) $id)->all();

        // Active catalogue skills can be added. An archived skill can only remain
        // attached if it was already associated with this professional.
        $eligibleIds = Skill::query()
            ->whereIn('id', $skillIds)
            ->where(function ($query) use ($existingIds): void {
                $query->where('status', 'active')
                    ->orWhereIn('id', $existingIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($eligibleIds) !== count(array_unique($skillIds))) {
            return back()->withErrors([
                'skill_ids' => 'Une ou plusieurs compétences ne sont plus disponibles. Actualisez la page et réessayez.',
            ])->withInput();
        }

        $proficiencyLevels = $validated['proficiency_levels'] ?? [];
        $skillYears = $validated['skill_years_experience'] ?? [];
        $syncData = [];

        foreach ($skillIds as $skillId) {
            $current = $existing->get($skillId);
            $level = array_key_exists($skillId, $proficiencyLevels)
                ? $proficiencyLevels[$skillId]
                : $current?->pivot->proficiency_level;
            $years = array_key_exists($skillId, $skillYears)
                ? $skillYears[$skillId]
                : $current?->pivot->years_experience;

            $syncData[$skillId] = [
                'proficiency_level' => $level,
                'years_experience' => $years === null ? null : (int) $years,
            ];
        }

        DB::transaction(function () use ($professional, $syncData): void {
            $lockedProfessional = $professional->newQuery()
                ->whereKey($professional->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProfessional->skills()->sync($syncData);
        });

        $this->auditLogService->record(
            'professional_profile_skills_updated',
            $professional,
            $request->user(),
            ['skill_count' => count($syncData)],
            $request,
        );

        return redirect()->route('professional.profile.edit')
            ->with('success', 'Vos compétences ont été mises à jour.');
    }

    public function services(Request $request): View
    {
        $this->authorize('viewAny', Service::class);

        $profile = $request->user()->professionalProfile()->firstOrFail();
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ServiceStatus::class)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $serviceCounts = $profile->services()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $services = $profile->services()
            ->with(['category', 'skills', 'images'])
            ->withCount('serviceRequests')
            ->when($validated['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('short_description', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['category_id'] ?? null, fn ($query, int $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return view('professional/services/index', [
            'services' => $services,
            'filters' => $validated,
            'statuses' => ServiceStatus::cases(),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'serviceCounts' => [
                'all' => (int) $serviceCounts->sum(),
                'published' => (int) ($serviceCounts[ServiceStatus::PUBLISHED->value] ?? 0),
                'draft' => (int) ($serviceCounts[ServiceStatus::DRAFT->value] ?? 0),
                'unpublished' => (int) ($serviceCounts[ServiceStatus::UNPUBLISHED->value] ?? 0),
                'archived' => (int) ($serviceCounts[ServiceStatus::ARCHIVED->value] ?? 0),
            ],
            'statusLabels' => [
                ServiceStatus::DRAFT->value => 'Brouillon',
                ServiceStatus::PUBLISHED->value => 'Publié',
                ServiceStatus::UNPUBLISHED->value => 'Dépublié',
                ServiceStatus::ARCHIVED->value => 'Archivé',
            ],
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

    public function previewService(Request $request, Service $service): View
    {
        $this->authorize('view', $service);

        return view('professional/services/preview', [
            'service' => $service->load(['category', 'skills', 'images', 'professionalProfile.user.profile']),
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

    public function replaceImage(ReplaceServiceImageRequest $request, ServiceImage $image): RedirectResponse
    {
        $this->serviceManager->replaceImage($image, $request->file('image'));

        return back()->with('success', 'Image remplacée avec succès.');
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
