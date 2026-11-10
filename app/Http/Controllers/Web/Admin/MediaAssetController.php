<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class MediaAssetController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.media.index', ['assets' => MediaAsset::query()->latest()->paginate(24)]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'alt_text' => ['required', 'string', 'max:255'],
        ]);

        $file = $data['file'];
        $path = $file->store('communication', 'public');
        abort_if($path === false, 500, 'Le fichier n’a pas pu être enregistré.');

        $asset = MediaAsset::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'width' => $file->getWidth(),
            'height' => $file->getHeight(),
            'alt_text' => $data['alt_text'],
            'uploaded_by' => $request->user()->id,
        ]);
        $audit->record('cms_media_uploaded', $asset, $request->user(), ['mime_type' => $asset->mime_type, 'size_bytes' => $asset->size_bytes], $request);

        return back()->with('success', 'Média public téléversé et enregistré dans la médiathèque.');
    }
}
