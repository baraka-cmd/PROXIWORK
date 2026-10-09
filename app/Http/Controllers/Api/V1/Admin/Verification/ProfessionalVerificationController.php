<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Verification\RejectProfessionalVerificationRequest;
use App\Http\Requests\Admin\Verification\StartProfessionalVerificationRequest;
use App\Http\Requests\Admin\Verification\VerifyProfessionalVerificationRequest;
use App\Http\Resources\Admin\Professional\AdminProfessionalResource;
use App\Models\ProfessionalProfile;
use App\Services\Admin\Verification\ProfessionalVerificationService;

class ProfessionalVerificationController extends Controller
{
    public function __construct(
        private readonly ProfessionalVerificationService $service,
    ) {}

    public function startReview(
        StartProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): AdminProfessionalResource {
        $updated = $this->service->startReview(
            $professional,
            $request->user(),
            $request,
            $request->string('note')->toString() ?: null,
        );

        return (new AdminProfessionalResource($updated))->additional([
            'message' => 'Vérification placée en cours de revue.',
            'meta' => ['scope' => 'admin.professionals.verification'],
        ]);
    }

    public function verify(
        VerifyProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): AdminProfessionalResource {
        $updated = $this->service->verify(
            $professional,
            $request->user(),
            $request,
            $request->string('note')->toString() ?: null,
        );

        return (new AdminProfessionalResource($updated))->additional([
            'message' => 'Professionnel vérifié avec succès.',
            'meta' => ['scope' => 'admin.professionals.verification'],
        ]);
    }

    public function reject(
        RejectProfessionalVerificationRequest $request,
        ProfessionalProfile $professional,
    ): AdminProfessionalResource {
        $updated = $this->service->reject(
            $professional,
            $request->user(),
            $request,
            $request->string('reason_code')->toString(),
            $request->string('note')->toString() ?: null,
        );

        return (new AdminProfessionalResource($updated))->additional([
            'message' => 'Vérification professionnelle rejetée.',
            'meta' => ['scope' => 'admin.professionals.verification'],
        ]);
    }
}
