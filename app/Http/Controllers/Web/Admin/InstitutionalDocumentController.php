<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalDocument;
use App\Models\InstitutionalDocumentVersion;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InstitutionalDocumentController extends Controller
{
    public function index(): View
    {
        return view('admin.communication.documents.index', [
            'documents' => InstitutionalDocument::query()->with('versions')->latest()->paginate(20),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['required', 'alpha_dash', 'max:180'],
            'document_type' => ['required', 'in:terms,privacy,professional_rules,refund_policy,payment_information,other'],
            'content' => ['required', 'string', 'max:50000'],
            'change_reason' => ['nullable', 'string', 'max:500'],
            'requires_acceptance' => ['sometimes', 'boolean'],
        ]);

        $document = DB::transaction(function () use ($data, $request): InstitutionalDocument {
            $document = InstitutionalDocument::query()->create([
                'title' => $data['title'],
                'slug' => Str::slug($data['slug']),
                'document_type' => $data['document_type'],
                'status' => 'draft',
                'current_version' => 1,
                'requires_acceptance' => $request->boolean('requires_acceptance'),
            ]);
            $document->versions()->create([
                'version' => 1,
                'content' => $data['content'],
                'change_reason' => $data['change_reason'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            return $document;
        });
        $audit->record('institutional_document_created', $document, $request->user(), ['version' => 1], $request);

        return back()->with('success', 'Document créé avec sa première version en brouillon.');
    }

    public function approve(Request $request, InstitutionalDocumentVersion $version, AuditLogService $audit): RedirectResponse
    {
        abort_if($version->created_by === $request->user()->id, 403, 'Une personne différente doit approuver cette version.');
        abort_unless($version->status === 'draft', 409);
        $version->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
        $audit->record('institutional_document_version_approved', $version, $request->user(), ['version' => $version->version], $request);

        return back()->with('success', 'Version approuvée.');
    }

    public function publish(Request $request, InstitutionalDocumentVersion $version, AuditLogService $audit): RedirectResponse
    {
        abort_unless($version->status === 'approved', 409, 'Seule une version approuvée peut être publiée.');
        DB::transaction(function () use ($version): void {
            $locked = InstitutionalDocumentVersion::query()->lockForUpdate()->findOrFail($version->id);
            abort_unless($locked->status === 'approved', 409);
            InstitutionalDocumentVersion::query()
                ->where('institutional_document_id', $locked->institutional_document_id)
                ->where('status', 'published')
                ->update(['status' => 'superseded']);
            $locked->update(['status' => 'published', 'published_at' => now(), 'effective_at' => $locked->effective_at ?? now()]);
            $locked->document()->update(['status' => 'published', 'current_version' => $locked->version]);
        });
        $audit->record('institutional_document_version_published', $version, $request->user(), ['version' => $version->version], $request);

        return back()->with('success', 'Version institutionnelle publiée.');
    }
}
