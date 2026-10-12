<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfessionalDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfessionalDocumentController extends Controller
{
    public function download(Request $request, ProfessionalDocument $document): StreamedResponse
    {
        $this->authorize('view', $document->professionalProfile);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404);

        return $disk->download(
            $document->path,
            basename($document->original_name),
            ['Content-Type' => $document->mime_type],
        );
    }
}