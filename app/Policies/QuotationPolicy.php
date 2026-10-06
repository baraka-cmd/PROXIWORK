<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;

class QuotationPolicy
{
    public function view(User $user, Quotation $quotation): bool
    {
        return $quotation->serviceRequest->client_id === $user->getKey()
            || $quotation->serviceRequest->professional?->user_id === $user->getKey();
    }

    public function create(User $user, ServiceRequest $serviceRequest): bool
    {
        return $user->hasRole('professional')
            && $serviceRequest->professional?->user_id === $user->getKey();
    }

    public function accept(User $user, Quotation $quotation): bool
    {
        return $user->hasRole('client')
            && $quotation->serviceRequest->client_id === $user->getKey();
    }

    public function reject(User $user, Quotation $quotation): bool
    {
        return $user->hasRole('client')
            && $quotation->serviceRequest->client_id === $user->getKey();
    }

    public function createOffer(User $user, Quotation $quotation): bool
    {
        $request = $quotation->serviceRequest;

        return ($user->hasRole('client') && $request->client_id === $user->getKey())
            || ($user->hasRole('professional') && $request->professional?->user_id === $user->getKey());
    }
}
