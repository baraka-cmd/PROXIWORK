<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\UserAccountStatus;
use App\Http\Requests\Web\RegisterRequest;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegisterController
{
    public function create(): View
    {
        $categories = Category::query()
            ->active()
            ->root()
            ->with(['skills' => fn ($query) => $query->active()->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('auth.register', compact('categories'));
    }

    public function store(RegisterRequest $request, DatabaseManager $database, AuditLogService $auditLogService): RedirectResponse
    {
        $data = $request->validated();
        $accountType = $data['account_type'];
        $storedPublicFiles = [];
        $storedPrivateFiles = [];

        try {
            $user = $database->transaction(function () use (
                $request, $data, $accountType, &$storedPublicFiles, &$storedPrivateFiles
            ): User {
                $role = Role::query()->where('name', $accountType)->firstOrFail();

                $firstName = trim($data['first_name']);
                $lastName = trim($data['last_name']);
                $email = mb_strtolower(trim($data['email']));

                $user = User::create([
                    'name' => trim($firstName.' '.$lastName),
                    'email' => $email,
                    'password' => $data['password'],
                    'account_status' => UserAccountStatus::ACTIVE,
                ]);
                $user->assignRole($role);

                $avatarPath = null;
                if ($request->hasFile('avatar')) {
                    $avatarPath = $request->file('avatar')->store('avatars', 'public');
                    $storedPublicFiles[] = $avatarPath;
                }

                $user->profile()->create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $data['phone'] ?? null,
                    'avatar_path' => $avatarPath,
                ]);

                $user->notificationPreference()->create([
                    'database_enabled' => true,
                    'email_enabled' => true,
                    'sms_enabled' => false,
                    'push_enabled' => false,
                ]);

                if ($accountType !== 'professional') {
                    return $user;
                }

                $profile = $user->professionalProfile()->create([
                    'business_name' => $data['business_name'] ?? null,
                    'city' => trim($data['city']),
                    'province' => $data['province'] ?? null,
                    'commune' => $data['commune'] ?? null,
                    'status' => 'draft',
                    'visibility' => 'private',
                ]);
                $profile->forceFill([
                    'verification_status' => ProfessionalVerificationStatus::PENDING,
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
                    // Documents are deliberately stored on the non-public local disk.
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

                return $user;
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

        event(new Registered($user));
        $auditLogService->record('auth.web.registered', $user, $user, ['account_type' => $accountType], $request);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        if ($accountType === 'professional') {
            return redirect()->route('professional.dashboard')->with(
                'status',
                'Votre dossier professionnel a été transmis. Votre compte reste privé jusqu’à la vérification du profil et la validation des services.'
            );
        }

        return redirect()->route('client.dashboard')->with(
            'status',
            'Votre compte client PROXIWORK a été créé. Vous pouvez maintenant rechercher des services et compléter votre profil.'
        );
    }
}
