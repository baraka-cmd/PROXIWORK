<?php

declare(strict_types=1);

namespace App\Services\Admin\Verification;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalProfile;
use App\Models\ProfessionalVerificationReview;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProfessionalVerificationService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function startReview(ProfessionalProfile $professional, User $admin, Request $request, ?string $note = null): ProfessionalProfile
    {
        return $this->transition(
            $professional,
            $admin,
            $request,
            ProfessionalVerificationStatus::UNDER_REVIEW,
            null,
            $note,
            'admin.professional.verification.started',
        );
    }

    public function verify(ProfessionalProfile $professional, User $admin, Request $request, ?string $note = null): ProfessionalProfile
    {
        $updated = $this->transition(
            $professional,
            $admin,
            $request,
            ProfessionalVerificationStatus::VERIFIED,
            null,
            $note,
            'admin.professional.verified',
        );

        $updated->user->notify(new AccountActivityNotification(
            'Profil professionnel vérifié',
            'Votre profil professionnel PROXIWORK a été vérifié par l’administration.',
            'professional_verified',
        ));

        return $updated;
    }

    public function requestInformation(
        ProfessionalProfile $professional,
        User $admin,
        Request $request,
        string $note,
    ): ProfessionalProfile {
        $updated = $this->transition(
            $professional,
            $admin,
            $request,
            ProfessionalVerificationStatus::NEEDS_INFORMATION,
            'PROFILE_INCOMPLETE',
            $note,
            'admin.professional.verification.information_requested',
        );

        $updated->user->notify(new AccountActivityNotification(
            'Informations complémentaires requises',
            'L’administration demande des corrections ou des pièces complémentaires. Consultez votre espace professionnel avant de soumettre à nouveau votre dossier.',
            'professional_information_required',
        ));

        return $updated;
    }

    public function reject(
        ProfessionalProfile $professional,
        User $admin,
        Request $request,
        string $reasonCode,
        ?string $note = null,
    ): ProfessionalProfile {
        $updated = $this->transition(
            $professional,
            $admin,
            $request,
            ProfessionalVerificationStatus::REJECTED,
            $reasonCode,
            $note,
            'admin.professional.verification.rejected',
        );

        $updated->user->notify(new AccountActivityNotification(
            'Vérification professionnelle rejetée',
            'La vérification de votre profil professionnel a été rejetée. Consultez le motif communiqué par l’administration.',
            'professional_verification_rejected',
        ));

        return $updated;
    }

    private function transition(
        ProfessionalProfile $professional,
        User $admin,
        Request $request,
        ProfessionalVerificationStatus $to,
        ?string $reasonCode,
        ?string $note,
        string $auditAction,
    ): ProfessionalProfile {
        return DB::transaction(function () use ($professional, $admin, $request, $to, $reasonCode, $note, $auditAction): ProfessionalProfile {
            $target = ProfessionalProfile::query()
                ->lockForUpdate()
                ->findOrFail($professional->getKey());

            $from = $target->verification_status;

            if (! $this->isAllowedTransition($from, $to)) {
                throw ValidationException::withMessages([
                    'verification_status' => [sprintf(
                        'Transition impossible : %s → %s.',
                        $from->value,
                        $to->value,
                    )],
                ]);
            }

            $target->verification_status = $to->value;
            if ($to !== ProfessionalVerificationStatus::VERIFIED) {
                $target->forceFill([
                    'status' => ProfessionalProfile::STATUS_DRAFT,
                    'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
                ]);
            }
            $target->save();

            ProfessionalVerificationReview::create([
                'professional_profile_id' => $target->getKey(),
                'admin_user_id' => $admin->getAuthIdentifier(),
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason_code' => $reasonCode,
                'note' => $note,
            ]);

            $this->auditLogService->record(
                $auditAction,
                $target,
                $admin,
                [
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                    'reason_code' => $reasonCode,
                ],
                $request,
            );

            return $target->fresh(['user.roles']);
        });
    }

    private function isAllowedTransition(
        ProfessionalVerificationStatus $from,
        ProfessionalVerificationStatus $to,
    ): bool {
        return match ($from) {
            ProfessionalVerificationStatus::PENDING => $to === ProfessionalVerificationStatus::UNDER_REVIEW,
            ProfessionalVerificationStatus::UNDER_REVIEW => in_array($to, [
                ProfessionalVerificationStatus::VERIFIED,
                ProfessionalVerificationStatus::REJECTED,
                ProfessionalVerificationStatus::NEEDS_INFORMATION,
            ], true),
            ProfessionalVerificationStatus::NEEDS_INFORMATION => $to === ProfessionalVerificationStatus::UNDER_REVIEW,
            ProfessionalVerificationStatus::VERIFIED,
            ProfessionalVerificationStatus::REJECTED => false,
        };
    }
}
