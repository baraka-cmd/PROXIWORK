<?php

declare(strict_types=1);

namespace App\Services\Admin\Verification;

use App\Enums\ProfessionalVerificationStatus;
use App\Models\ProfessionalDocument;
use App\Models\ProfessionalDocumentReview;
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

    public function requestAdditionalDocuments(
        ProfessionalProfile $professional,
        User $admin,
        Request $request,
        string $instructions,
    ): ProfessionalProfile {
        $updated = $this->transition(
            $professional,
            $admin,
            $request,
            ProfessionalVerificationStatus::ADDITIONAL_DOCUMENTS_REQUESTED,
            'ADDITIONAL_DOCUMENTS_REQUESTED',
            $instructions,
            'admin.professional.verification.documents_requested',
        );

        $updated->user->notify(new AccountActivityNotification(
            'Documents complémentaires requis',
            $instructions,
            'professional_verification_documents_requested',
            '/professional/documents',
        ));

        return $updated;
    }

    public function verify(ProfessionalProfile $professional, User $admin, Request $request, ?string $note = null): ProfessionalProfile
    {
        $updated = DB::transaction(function () use ($professional, $admin, $request, $note): ProfessionalProfile {
            $target = ProfessionalProfile::query()->lockForUpdate()->findOrFail($professional->getKey());

            if (! $this->isAllowedTransition($target->verification_status, ProfessionalVerificationStatus::VERIFIED)) {
                throw ValidationException::withMessages([
                    'verification_status' => [sprintf(
                        'Transition impossible : %s → %s.',
                        $target->verification_status->value,
                        ProfessionalVerificationStatus::VERIFIED->value,
                    )],
                ]);
            }

            if (! $target->documents()->exists()) {
                throw ValidationException::withMessages([
                    'documents' => ['Impossible de vérifier le professionnel : aucun justificatif n’a été transmis.'],
                ]);
            }

            $latestDocumentIds = $target->documents()
                ->selectRaw('MAX(id)')
                ->groupBy('document_type');

            if ($target->documents()
                ->whereIn('id', $latestDocumentIds)
                ->where('status', '!=', ProfessionalDocument::STATUS_APPROVED)
                ->exists()) {
                throw ValidationException::withMessages([
                    'documents' => ['Le dernier justificatif transmis pour chaque catégorie doit être approuvé avant la validation finale.'],
                ]);
            }

            return $this->transitionLocked(
                $target,
                $admin,
                $request,
                ProfessionalVerificationStatus::VERIFIED,
                null,
                $note,
                'admin.professional.verified',
            );
        });

        $updated->user->notify(new AccountActivityNotification(
            'Profil professionnel vérifié',
            'Votre profil professionnel PROXIWORK a été vérifié par l’administration.',
            'professional_verified',
        ));

        return $updated;
    }

    public function reject(
        ProfessionalProfile $professional,
        User $admin,
        Request $request,
        string $reasonCode,
        string $note,
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
            $note,
            'professional_verification_rejected',
        ));

        return $updated;
    }

    public function reviewDocument(
        ProfessionalProfile $professional,
        ProfessionalDocument $document,
        User $admin,
        Request $request,
        string $decision,
        ?string $note = null,
    ): ProfessionalDocument {
        $updated = DB::transaction(function () use ($professional, $document, $admin, $request, $decision, $note): ProfessionalDocument {
            $target = ProfessionalDocument::query()->lockForUpdate()->findOrFail($document->getKey());

            if ((int) $target->professional_profile_id !== (int) $professional->getKey()) {
                abort(404);
            }

            if ($target->status !== ProfessionalDocument::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'document' => ['Ce justificatif a déjà fait l’objet d’une décision.'],
                ]);
            }

            if (! in_array($decision, [ProfessionalDocument::STATUS_APPROVED, ProfessionalDocument::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'decision' => ['Décision de document invalide.'],
                ]);
            }

            if ($decision === ProfessionalDocument::STATUS_REJECTED && trim((string) $note) === '') {
                throw ValidationException::withMessages([
                    'note' => ['Un motif est obligatoire pour rejeter un justificatif.'],
                ]);
            }

            $target->forceFill([
                'status' => $decision,
                'review_note' => $note,
                'reviewed_by' => $admin->getAuthIdentifier(),
                'reviewed_at' => now(),
            ])->save();

            ProfessionalDocumentReview::query()->create([
                'professional_document_id' => $target->getKey(),
                'admin_user_id' => $admin->getAuthIdentifier(),
                'decision' => $decision,
                'note' => $note,
            ]);

            $this->auditLogService->record(
                'admin.professional.document.'.($decision === ProfessionalDocument::STATUS_APPROVED ? 'approved' : 'rejected'),
                $target,
                $admin,
                ['professional_profile_id' => $professional->getKey(), 'decision' => $decision],
                $request,
            );

            return $target->fresh(['reviewer', 'reviews.admin']);
        });

        $professional->user()->first()?->notify(new AccountActivityNotification(
            $decision === ProfessionalDocument::STATUS_APPROVED ? 'Justificatif approuvé' : 'Justificatif à corriger',
            $decision === ProfessionalDocument::STATUS_APPROVED
                ? 'Un justificatif de votre dossier professionnel a été approuvé.'
                : ('Un justificatif de votre dossier professionnel doit être corrigé. '.trim((string) $note)),
            'professional_verification_document_'.$decision,
            '/professional/documents',
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

            return $this->transitionLocked($target, $admin, $request, $to, $reasonCode, $note, $auditAction);
        });
    }

    private function transitionLocked(
        ProfessionalProfile $target,
        User $admin,
        Request $request,
        ProfessionalVerificationStatus $to,
        ?string $reasonCode,
        ?string $note,
        string $auditAction,
    ): ProfessionalProfile {
        $from = $target->verification_status;

        if (! $this->isAllowedTransition($from, $to)) {
            throw ValidationException::withMessages([
                'verification_status' => [sprintf('Transition impossible : %s → %s.', $from->value, $to->value)],
            ]);
        }

        $target->forceFill([
            'verification_status' => $to->value,
            'verified_at' => $to === ProfessionalVerificationStatus::VERIFIED ? now() : null,
        ])->save();

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
            ['from_status' => $from->value, 'to_status' => $to->value, 'reason_code' => $reasonCode],
            $request,
        );

        return $target->fresh(['user.roles']);
    }

    private function isAllowedTransition(
        ProfessionalVerificationStatus $from,
        ProfessionalVerificationStatus $to,
    ): bool {
        return match ($from) {
            ProfessionalVerificationStatus::PENDING,
            ProfessionalVerificationStatus::ADDITIONAL_DOCUMENTS_REQUESTED => $to === ProfessionalVerificationStatus::UNDER_REVIEW,
            ProfessionalVerificationStatus::UNDER_REVIEW => in_array($to, [
                ProfessionalVerificationStatus::VERIFIED,
                ProfessionalVerificationStatus::REJECTED,
                ProfessionalVerificationStatus::ADDITIONAL_DOCUMENTS_REQUESTED,
            ], true),
            ProfessionalVerificationStatus::VERIFIED,
            ProfessionalVerificationStatus::REJECTED => false,
        };
    }
}
