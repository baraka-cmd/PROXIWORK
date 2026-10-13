<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ServiceRealization;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicServiceRealizationController extends Controller
{
    public function image(ServiceRealization $realization): BinaryFileResponse
    {
        abort_unless(
            $realization->status === ServiceRealization::STATUS_APPROVED
                && $realization->participation_confirmed
                && $realization->service->isRequestableForClient(),
            404,
        );

        abort_unless(Storage::disk('local')->exists($realization->path), 404);

        $mimeType = Storage::disk('local')->mimeType($realization->path);
        abort_unless(
            is_string($mimeType) && in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true),
            404,
        );

        $response = response()->file(Storage::disk('local')->path($realization->path));
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
