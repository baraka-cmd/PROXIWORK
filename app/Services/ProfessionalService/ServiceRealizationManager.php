<?php

declare(strict_types=1);

namespace App\Services\ProfessionalService;

use App\Enums\ServiceStatus;
use Illuminate\Auth\Access\AuthorizationException;
use App\Models\Service;
use App\Models\ServiceRealization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServiceRealizationManager
{
    public function submit(Service $service, UploadedFile $file, array $attributes): ServiceRealization
    {
        $path = $file->store('service-realizations/'.$service->getKey(), 'local');

        if (is_string($path) === false || $path === '') {
            throw ValidationException::withMessages([
                'image' => 'La preuve n’a pas pu être enregistrée. Réessayez avec une autre image.',
            ]);
        }

        try {
            return DB::transaction(function () use ($service, $path, $attributes): ServiceRealization {
                $lockedService = Service::query()->whereKey($service->getKey())->lockForUpdate()->firstOrFail();

                if ($lockedService->status === ServiceStatus::ARCHIVED) {
                    throw ValidationException::withMessages([
                        'service' => 'Une réalisation ne peut pas être ajoutée à un service archivé.',
                    ]);
                }

                return $lockedService->realizations()->create([
                    'path' => $path,
                    'caption' => $attributes['caption'],
                    'completed_at' => $attributes['completed_at'] ?? null,
                    'participation_confirmed' => true,
                    'status' => ServiceRealization::STATUS_PENDING,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    public function delete(ServiceRealization $realization, User $owner): void
    {
        $path = DB::transaction(function () use ($realization, $owner): string {
            $locked = ServiceRealization::query()
                ->with('service.professionalProfile')
                ->whereKey($realization->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->service->professionalProfile->user_id !== $owner->getKey()) {
                throw new AuthorizationException('Vous ne pouvez pas supprimer une réalisation qui ne vous appartient pas.');
            }

            if ($locked->status === ServiceRealization::STATUS_APPROVED) {
                throw ValidationException::withMessages([
                    'realization' => 'Une réalisation approuvée ne peut pas être supprimée directement. Contactez le support si son retrait est nécessaire.',
                ]);
            }

            $path = $locked->path;
            $locked->delete();

            return $path;
        });

        Storage::disk('local')->delete($path);
    }

    public function approve(ServiceRealization $realization, User $reviewer, ?string $note = null): ServiceRealization
    {
        return DB::transaction(function () use ($realization, $reviewer, $note): ServiceRealization {
            $locked = ServiceRealization::query()->whereKey($realization->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ServiceRealization::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'realization' => 'Seules les réalisations en attente peuvent être modérées.',
                ]);
            }

            // A submitted file alone is not sufficient: the professional must attest to their participation.
            if ($locked->participation_confirmed === false) {
                throw ValidationException::withMessages([
                    'realization' => 'La déclaration de participation doit être confirmée avant approbation.',
                ]);
            }

            if (Storage::disk('local')->exists($locked->path) === false) {
                throw ValidationException::withMessages([
                    'realization' => 'Le fichier de cette réalisation est introuvable. Elle ne peut pas être publiée.',
                ]);
            }

            $locked->forceFill([
                'status' => ServiceRealization::STATUS_APPROVED,
                'review_note' => $note,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }

    public function reject(ServiceRealization $realization, User $reviewer, string $note): ServiceRealization
    {
        return DB::transaction(function () use ($realization, $reviewer, $note): ServiceRealization {
            $locked = ServiceRealization::query()->whereKey($realization->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ServiceRealization::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'realization' => 'Seules les réalisations en attente peuvent être modérées.',
                ]);
            }

            $locked->forceFill([
                'status' => ServiceRealization::STATUS_REJECTED,
                'review_note' => $note,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
            ])->save();

            return $locked->refresh();
        });
    }
}
