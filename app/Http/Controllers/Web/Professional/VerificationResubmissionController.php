<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Professional;

use App\Enums\ProfessionalVerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ProfessionalProfile;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerificationResubmissionController extends Controller
{
    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $request->validate([
            'confirm' => ['accepted'],
        ]);

        $profile = $request->user()->professionalProfile()->firstOrFail();

        DB::transaction(function () use ($profile, $request, $auditLogService): void {
            $locked = ProfessionalProfile::query()->lockForUpdate()->findOrFail($profile->getKey());

            if ($locked->verification_status !== ProfessionalVerificationStatus::NEEDS_INFORMATION) {
                throw ValidationException::withMessages([
                    'verification_status' => 'Votre dossier ne demande pas de complément en ce moment.',
                ]);
            }

            $locked->forceFill([
                'verification_status' => ProfessionalVerificationStatus::PENDING,
                'status' => ProfessionalProfile::STATUS_DRAFT,
                'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
            ])->save();

            $auditLogService->record(
                'professional.verification.resubmitted',
                $locked,
                $request->user(),
                [
                    'from_status' => ProfessionalVerificationStatus::NEEDS_INFORMATION->value,
                    'to_status' => ProfessionalVerificationStatus::PENDING->value,
                ],
                $request,
            );
        });

        return redirect()->route('professional.dashboard')->with(
            'success',
            'Votre dossier a été soumis à nouveau et attend une nouvelle revue. Votre profil reste privé pendant cette période.'
        );
    }
}
