<?php

declare(strict_types=1);

namespace App\Services\ProfessionalService;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\SkillStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Service;
use App\Models\ServiceImage;
use App\Models\Skill;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProfessionalServiceManager
{
    public function create(ProfessionalProfile $profile, array $attributes): Service
    {
        return DB::transaction(function () use ($profile, $attributes): Service {
            $category = Category::query()->lockForUpdate()->findOrFail($attributes['category_id']);
            $this->ensureCategoryIsActive($category);

            $skillIds = $attributes['skill_ids'] ?? [];
            $this->ensureSkillsAreActive($skillIds);

            $service = $this->createWithUniqueSlug(
                $profile,
                $this->serviceAttributes($attributes),
            );

            $service->skills()->sync($skillIds);

            return $service->load(['category', 'skills', 'images']);
        });
    }

    public function update(Service $service, array $attributes): Service
    {
        return DB::transaction(function () use ($service, $attributes): Service {
            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('category_id', $attributes)) {
                $category = Category::query()->lockForUpdate()->findOrFail($attributes['category_id']);
                $this->ensureCategoryIsActive($category);
            }

            if (array_key_exists('skill_ids', $attributes)) {
                $this->ensureSkillsAreActive($attributes['skill_ids']);
                $service->skills()->sync($attributes['skill_ids']);
            }

            $service->update($this->serviceAttributes($attributes));

            return $service->refresh()->load(['category', 'skills', 'images']);
        });
    }

    public function archive(Service $service): void
    {
        DB::transaction(function () use ($service): void {
            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            $service->forceFill([
                'status' => ServiceStatus::ARCHIVED,
                'published_at' => null,
            ])->save();
        });
    }

    public function publish(Service $service): Service
    {
        return DB::transaction(function () use ($service): Service {
            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();
            $service->load(['category', 'skills', 'images']);

            if ($service->status === ServiceStatus::ARCHIVED) {
                throw ValidationException::withMessages([
                    'status' => 'Un service archivé ne peut pas être publié.',
                ]);
            }

            $this->ensureCategoryIsActive($service->category);
            $this->ensurePricingIsCoherent($service);

            $professional = ProfessionalProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($service->professional_profile_id);

            if ($professional->verification_status !== ProfessionalVerificationStatus::VERIFIED) {
                throw ValidationException::withMessages([
                    'professional' => 'Le profil professionnel doit être vérifié avant la publication d’un service.',
                ]);
            }

            if ($service->images->where('is_cover', true)->isEmpty()) {
                throw ValidationException::withMessages([
                    'images' => 'Une image de couverture est obligatoire pour publier le service.',
                ]);
            }

            $inactiveSkill = $service->skills->first(
                fn (Skill $skill): bool => $skill->status !== SkillStatus::ACTIVE
            );

            if ($inactiveSkill !== null) {
                throw ValidationException::withMessages([
                    'skill_ids' => 'Toutes les compétences d’un service publié doivent être actives.',
                ]);
            }

            $service->forceFill([
                'status' => ServiceStatus::PUBLISHED,
                'published_at' => now(),
            ])->save();

            if ($professional->user->account_status !== UserAccountStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'professional' => 'Un compte inactif ne peut pas publier de service.',
                ]);
            }

            $professional->forceFill([
                'status' => ProfessionalProfile::STATUS_ACTIVE,
                'visibility' => ProfessionalProfile::VISIBILITY_PUBLIC,
            ])->save();

            return $service->refresh()->load(['category', 'skills', 'images']);
        });
    }

    public function unpublish(Service $service): Service
    {
        return DB::transaction(function () use ($service): Service {
            $service = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();

            if ($service->status === ServiceStatus::ARCHIVED) {
                throw ValidationException::withMessages([
                    'status' => 'Un service archivé ne peut pas être dépublié.',
                ]);
            }

            $service->forceFill([
                'status' => ServiceStatus::UNPUBLISHED,
                'published_at' => null,
            ])->save();

            return $service->refresh();
        });
    }

    public function addImage(Service $service, UploadedFile $file, array $attributes): ServiceImage
    {
        $path = $file->store('services/'.$service->getKey(), 'public');

        try {
            return DB::transaction(function () use ($service, $path, $attributes): ServiceImage {
                $lockedService = Service::query()
                    ->whereKey($service->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedService->images()->count() >= 8) {
                    throw ValidationException::withMessages([
                        'image' => 'Un service ne peut pas contenir plus de 8 images.',
                    ]);
                }

                $hasImages = $lockedService->images()->exists();
                $isCover = (bool) ($attributes['is_cover'] ?? false) || $hasImages === false;

                if ($isCover) {
                    $lockedService->images()->update(['is_cover' => false]);
                }

                return $lockedService->images()->create([
                    'path' => $path,
                    'alt_text' => $attributes['alt_text'] ?? null,
                    'sort_order' => $attributes['sort_order'] ?? 0,
                    'is_cover' => $isCover,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
    }

    public function replaceImage(ServiceImage $image, UploadedFile $file): ServiceImage
    {
        $newPath = $file->store('services/'.$image->service_id, 'public');

        if (! is_string($newPath) || $newPath === '') {
            throw ValidationException::withMessages([
                'image' => 'La nouvelle image n’a pas pu être enregistrée. L’image actuelle a été conservée.',
            ]);
        }

        try {
            [$updatedImage, $oldPath] = DB::transaction(function () use ($image, $newPath): array {
                $service = Service::query()->whereKey($image->service_id)->lockForUpdate()->firstOrFail();
                $lockedImage = $service->images()->whereKey($image->getKey())->lockForUpdate()->firstOrFail();
                $oldPath = $lockedImage->path;
                $lockedImage->update(['path' => $newPath]);

                return [$lockedImage->refresh(), $oldPath];
            });

            if ($oldPath !== $newPath) {
                Storage::disk('public')->delete($oldPath);
            }

            return $updatedImage;
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }
    }

    public function updateImage(ServiceImage $image, array $attributes): ServiceImage
    {
        return DB::transaction(function () use ($image, $attributes): ServiceImage {
            $service = Service::query()->whereKey($image->service_id)->lockForUpdate()->firstOrFail();
            $lockedImage = $service->images()->whereKey($image->getKey())->lockForUpdate()->firstOrFail();
            $hasCoverInput = array_key_exists('is_cover', $attributes);
            $isCover = $hasCoverInput && filter_var($attributes['is_cover'], FILTER_VALIDATE_BOOLEAN);

            if ($isCover) {
                $service->images()
                    ->where('id', '!=', $lockedImage->getKey())
                    ->update(['is_cover' => false]);
            }

            if ($hasCoverInput && ! $isCover && $lockedImage->is_cover) {
                $replacement = $service->images()
                    ->where('id', '!=', $lockedImage->getKey())
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($replacement === null) {
                    throw ValidationException::withMessages([
                        'is_cover' => 'Le service doit conserver une image de couverture.',
                    ]);
                }

                $replacement->update(['is_cover' => true]);
            }

            if ($hasCoverInput) {
                $attributes['is_cover'] = $isCover;
            }

            $lockedImage->update($attributes);

            return $lockedImage->refresh();
        });
    }

    public function deleteImage(ServiceImage $image): void
    {
        $path = DB::transaction(function () use ($image): string {
            $service = Service::query()->whereKey($image->service_id)->lockForUpdate()->firstOrFail();
            $lockedImage = $service->images()->whereKey($image->getKey())->lockForUpdate()->firstOrFail();
            $path = $lockedImage->path;
            $wasCover = $lockedImage->is_cover;

            $lockedImage->delete();

            if ($wasCover) {
                $replacement = $service->images()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                $replacement?->update(['is_cover' => true]);
            }

            return $path;
        });

        Storage::disk('public')->delete($path);
    }

    private function serviceAttributes(array $attributes): array
    {
        return collect($attributes)
            ->only([
                'category_id',
                'title',
                'short_description',
                'description',
                'included_items',
                'excluded_items',
                'client_requirements',
                'deliverables',
                'commercial_conditions',
                'execution_mode',
                'pricing_type',
                'price',
                'price_min',
                'price_max',
                'currency',
                'estimated_duration_minutes',
                'sort_order',
            ])
            ->all();
    }

    private function ensureCategoryIsActive(Category $category): void
    {
        if ($category->status !== CategoryStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'category_id' => 'La catégorie sélectionnée doit être active.',
            ]);
        }
    }

    private function ensureSkillsAreActive(array $skillIds): void
    {
        if ($skillIds === []) {
            return;
        }

        $activeCount = Skill::query()
            ->whereIn('id', $skillIds)
            ->where('status', SkillStatus::ACTIVE->value)
            ->count();

        if ($activeCount !== count(array_unique($skillIds))) {
            throw ValidationException::withMessages([
                'skill_ids' => 'Toutes les compétences sélectionnées doivent être actives.',
            ]);
        }
    }

    private function ensurePricingIsCoherent(Service $service): void
    {
        if ($service->pricing_type === ServicePricingType::FIXED || $service->pricing_type === ServicePricingType::FROM) {
            if ($service->price === null || $service->currency === null) {
                throw ValidationException::withMessages([
                    'pricing' => 'Le prix et la devise sont obligatoires pour ce service.',
                ]);
            }
        }

        if ($service->pricing_type === ServicePricingType::RANGE) {
            if ($service->price_min === null || $service->price_max === null || $service->price_max < $service->price_min || $service->currency === null) {
                throw ValidationException::withMessages([
                    'pricing' => 'La fourchette de prix et la devise sont invalides.',
                ]);
            }
        }

        if ($service->pricing_type === ServicePricingType::QUOTE
            && ($service->price !== null || $service->price_min !== null || $service->price_max !== null)) {
            throw ValidationException::withMessages([
                'pricing' => 'Un service sur devis ne doit pas conserver de prix.',
            ]);
        }
    }

    private function createWithUniqueSlug(ProfessionalProfile $profile, array $attributes): Service
    {
        $base = Str::slug($attributes['title']) ?: 'service';

        try {
            return $profile->services()->create([
                ...$attributes,
                'slug' => $this->uniqueSlug($base),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $profile->services()->create([
                ...$attributes,
                'slug' => $base.'-'.Str::lower(Str::random(8)),
            ]);
        }
    }

    private function uniqueSlug(string $base): string
    {

        $candidate = $base;
        $suffix = 2;

        while (Service::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
