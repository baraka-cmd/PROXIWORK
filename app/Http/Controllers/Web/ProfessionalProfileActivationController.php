<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ProfessionalProfileActivationRequest;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountActivityNotification;
use App\Services\Audit\AuditLogService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ProfessionalProfileActivationController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->hasRole('client'), 403);
        abort_if($user->hasRole('professional') || $user->professionalProfile()->exists(), 409, 'Un espace professionnel existe déjà pour ce compte.');

        $categories = Category::query()
            ->active()
            ->root()
            ->with(['skills' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('auth.professional-activation', [
            'categories' => $categories,
            'user' => $user->load('profile'),
            'activationMode' => true,
        ]);
    }

    public function store(
        ProfessionalProfileActivationRequest $request,
        DatabaseManager $database,
        AuditLogService $auditLogService,
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        $storedPublicFiles = [];
        $storedPrivateFiles = [];

        try {
            $database->transaction(function () use (
                $request,
                $user,
                $data,
                &$storedPublicFiles,
                &$storedPrivateFiles,
            ): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

                if ($lockedUser->hasRole('professional') || $lockedUser->professionalProfile()->exists()) {
                    throw new ConflictHttpException('Un espace professionnel existe déjà pour ce compte.');
                }

                $professionalRole = Role::query()->where('name', 'professional')->firstOrFail();

                $profile = $lockedUser->professionalProfile()->create([
                    'business_name' => $data['business_name'] ?? null,
                    'city' => trim($data['city']),
                    'province' => $data['province'] ?? null,
                    'commune' => $data['commune'] ?? null,
                ]);
                $profile->forceFill([
                    'status' => 'draft',
                    'visibility' => 'private',
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
                    'professional_terms_accepted_at' => now(),
                ])->save();

                $profile->categories()->sync($data['category_ids']);
                $profile->skills()->sync(collect($data['skill_ids'])->mapWithKeys(
                    fn ($skillId) => [(int) $skillId => ['proficiency_level' => 'self_declared']]
                )->all());

                foreach ($data['services'] as $serviceData) {
                    $title = trim($serviceData['title']);
                    $service = $profile->services()->create([
                        'category_id' => (int) $serviceData['category_id'],
                        'title' => $title,
                        'slug' => Str::slug($title).'-'.Str::lower(Str::random(10)),
                        'short_description' => Str::limit(trim($serviceData['description']), 280),
                        'description' => trim($serviceData['description']),
                        'pricing_type' => $serviceData['pricing_type'],
                        'price' => ($serviceData['pricing_type'] ?? 'quote') === 'fixed' ? ($serviceData['price'] ?? null) : null,
                        'price_min' => match ($serviceData['pricing_type'] ?? 'quote') {
                            'from' => $serviceData['price'] ?? null,
                            'range' => $serviceData['price_min'] ?? null,
                            default => null,
                        },
                        'price_max' => ($serviceData['pricing_type'] ?? 'quote') === 'range' ? ($serviceData['price_max'] ?? null) : null,
                        'currency' => $serviceData['currency'] ?? null,
                        'billing_unit' => $serviceData['billing_unit'] ?? null,
                        'estimated_duration_minutes' => $serviceData['estimated_duration_minutes'] ?? null,
                        'service_area' => $serviceData['service_area'] ?? null,
                        'conditions' => $serviceData['conditions'] ?? null,
                        'status' => 'draft',
                        'published_at' => null,
                    ]);

                    $service->skills()->sync($serviceData['skill_ids']);

                    foreach (($serviceData['images'] ?? []) as $position => $image) {
                        $path = $image->store('service-images', 'public');
                        $storedPublicFiles[] = $path;
                        $service->images()->create([
                            'path' => $path,
                            'alt_text' => 'Illustration du service '.$title,
                            'sort_order' => $position,
                            'is_cover' => $position === 0,
                        ]);
                    }
                }

                foreach (($data['documents'] ?? []) as $documentData) {
                    if (empty($documentData['file'])) {
                        continue;
                    }

                    $file = $documentData['file'];
                    $path = $file->store('professional-documents', 'local');
                    $storedPrivateFiles[] = $path;

                    $profile->documents()->create([
                        'document_type' => $documentData['type'],
                        'path' => $path,
                        'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size_bytes' => $file->getSize(),
                        'review_status' => 'pending',
                        'submitted_at' => now(),
                    ]);
                }

                $lockedUser->assignRole($professionalRole);
            });
        } catch (Throwable $exception) {
            foreach ($storedPublicFiles as $path) {
                Storage::disk('public')->delete($path);
            }
            foreach ($storedPrivateFiles as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $user->refresh();
        $auditLogService->record('auth.web.professional_profile_activated', $user->professionalProfile, $user, [], $request);
        $user->notify(new AccountActivityNotification(
            'Espace professionnel créé',
            'Votre espace professionnel PROXIWORK a été créé. Votre dossier est en attente de vérification et votre profil reste privé jusqu’aux validations nécessaires.',
            'professional_profile_activated',
        ));

        $request->session()->forget('active_workspace');
        $request->session()->regenerate();
        $request->session()->put('auth.session_version', (int) $user->session_version);
        $request->session()->put('active_workspace', 'professional');

        return redirect()->route('professional.dashboard')->with(
            'status',
            'Votre espace professionnel a été ajouté à votre compte existant. Votre espace client et son historique sont conservés.'
        );
    }
}
