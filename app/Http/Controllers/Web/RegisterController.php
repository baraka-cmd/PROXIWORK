<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceStatus;
use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\RegisterRequest;
use App\Models\Category;
use App\Models\ProfessionalProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function createClient(): View
    {
        return view('auth.register-client');
    }

    public function createProfessional(): View
    {
        $categories = $this->professionalRegistrationCategories();

        return view('auth.register-professional', compact('categories'));
    }

    public function createProfessionalApplication(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('client'), 403);
        abort_if($user->professionalProfile()->exists(), 409, 'Un profil professionnel est déjà associé à ce compte.');

        $categories = $this->professionalRegistrationCategories();

        return view('auth.register-professional', [
            'categories' => $categories,
            'existingUser' => $user->load('profile'),
        ]);
    }

    private function professionalRegistrationCategories(): Collection
    {
        $categories = Category::query()
            ->active()
            ->root()
            ->with([
                'skills' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                'children' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
                'children.skills' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories->each(function (Category $category): void {
            $categorySkills = $category->skills->concat(
                $category->children->flatMap(fn (Category $subcategory) => $subcategory->skills),
            );

            $category->setRelation('skills', $categorySkills->sortBy('name')->values());
        });

        return $categories;
    }

    public function store(RegisterRequest $request, DatabaseManager $database): RedirectResponse
    {
        $isExistingAccount = $request->routeIs('client.professional-profile.store');
        $accountType = $request->routeIs('register.professional.store', 'client.professional-profile.store') ? 'professional' : 'client';
        $data = $request->validated();
        $storedFiles = [];
        $profilePhotoPath = null;
        $documents = [];

        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $request->file('profile_photo')->store('avatars', 'local');
            $storedFiles[] = $profilePhotoPath;
        }

        foreach ($data['documents'] ?? [] as $documentData) {
            $file = $documentData['file'] ?? null;

            if ($file === null) {
                continue;
            }

            $path = $file->store('professional-documents/registration', 'local');
            $storedFiles[] = $path;
            $documents[] = [
                'document_type' => $documentData['type'],
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'status' => 'pending',
            ];
        }

        try {
            $user = $database->transaction(function () use ($request, $data, $accountType, $profilePhotoPath, $documents, $isExistingAccount): User {
                if ($isExistingAccount) {
                    $user = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
                    abort_unless($user->hasRole('client') && $user->professionalProfile()->doesntExist(), 409);
                    $user->assignRole(Role::query()->where('name', 'professional')->firstOrFail());
                } else {
                    $firstName = trim($request->string('first_name')->toString());
                    $lastName = trim($request->string('last_name')->toString());
                    $user = User::query()->create([
                        'name' => $firstName.' '.$lastName,
                        'email' => strtolower(trim($request->string('email')->toString())),
                        'password' => $request->string('password')->toString(),
                        'account_status' => UserAccountStatus::ACTIVE,
                    ]);

                    $user->assignRole(Role::query()->where('name', $accountType)->firstOrFail());
                    $user->forceFill([
                        'terms_accepted_at' => now(),
                        'privacy_accepted_at' => now(),
                    ])->save();
                    $user->profile()->create([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => trim($request->string('phone')->toString()),
                        'avatar_path' => $profilePhotoPath,
                    ]);
                    $user->notificationPreference()->create();
                }

                if ($accountType === 'professional') {
                    $professionalProfile = $user->professionalProfile()->create([
                        'professional_title' => trim((string) $data['professional_title']),
                        'business_name' => filled($data['business_name'] ?? null) ? trim((string) $data['business_name']) : null,
                        'province' => trim((string) $data['province']),
                        'city' => trim((string) $data['city']),
                        'service_radius_km' => (int) $data['service_radius_km'],
                        'years_experience' => filled($data['years_experience'] ?? null) ? (int) $data['years_experience'] : null,
                        'verification_status' => ProfessionalVerificationStatus::PENDING,
                        'status' => ProfessionalProfile::STATUS_DRAFT,
                        'visibility' => ProfessionalProfile::VISIBILITY_PRIVATE,
                    ]);

                    $professionalProfile->skills()->sync(collect($data['skill_ids'])->mapWithKeys(
                        fn (string|int $skillId): array => [(int) $skillId => [
                            'proficiency_level' => 'intermediate',
                            'years_experience' => filled($data['years_experience'] ?? null) ? (int) $data['years_experience'] : null,
                        ]],
                    )->all());

                    foreach ($data['services'] as $serviceData) {
                        $service = $professionalProfile->services()->create([
                            'category_id' => (int) $serviceData['category_id'],
                            'title' => trim($serviceData['title']),
                            'slug' => Str::slug($serviceData['title']).'-'.Str::lower(Str::random(8)),
                            'short_description' => Str::limit(trim($serviceData['description']), 280),
                            'description' => trim($serviceData['description']),
                            'pricing_type' => $serviceData['pricing_type'],
                            'price' => $serviceData['pricing_type'] === 'fixed' || $serviceData['pricing_type'] === 'from'
                                ? ($serviceData['price'] ?? null)
                                : null,
                            'price_min' => $serviceData['pricing_type'] === 'range' ? ($serviceData['price_min'] ?? null) : null,
                            'price_max' => $serviceData['pricing_type'] === 'range' ? ($serviceData['price_max'] ?? null) : null,
                            'currency' => $serviceData['currency'],
                            'execution_mode' => $serviceData['execution_mode'],
                            'estimated_duration_minutes' => $serviceData['estimated_duration_minutes'] ?? null,
                            'status' => ServiceStatus::DRAFT,
                        ]);
                        $service->skills()->sync(array_map('intval', $serviceData['skill_ids']));
                    }

                    foreach ($documents as $document) {
                        $professionalProfile->documents()->create($document);
                    }
                }

                return $user;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedFiles);

            throw $exception;
        }

        if (! $isExistingAccount) {
            event(new Registered($user));
            Auth::login($user);
        }
        $request->session()->regenerate();
        $request->session()->put('active_workspace', 'professional');

        if ($isExistingAccount) {
            return redirect()->route('professional.dashboard')->with(
                'success',
                'Votre dossier professionnel a été ajouté à votre compte client. Votre profil reste privé pendant la vérification.'
            );
        }

        if ($accountType === 'professional') {
            return redirect()->route('professional.dashboard')->with(
                'success',
                'Votre dossier a été transmis. Votre profil reste privé et vos services sont enregistrés comme brouillons pendant la vérification.'
            );
        }

        return redirect()->route('client.dashboard')->with(
            'success',
            'Votre compte client PROXIWORK a été créé avec succès.'
        );
    }
}
