<?php

declare(strict_types=1);

namespace App\Services\ProfessionalService;

use App\Enums\CategoryStatus;
use App\Enums\ProfessionalVerificationStatus;
use App\Enums\ServiceModerationStatus;
use App\Enums\ServicePricingType;
use App\Enums\ServiceStatus;
use App\Enums\SkillStatus;
use App\Enums\UserAccountStatus;
use App\Models\Category;
use App\Models\Service;
use App\Models\Skill;
use Illuminate\Validation\ValidationException;

class ServicePublicationEligibility
{
    public function assertProfessionalCanPublish(Service $service): void
    {
        $this->assertCommonRequirements($service);

        $profile = $service->professionalProfile;
        $user = $profile?->user;

        if ($user === null || $user->account_status !== UserAccountStatus::ACTIVE || ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'professional' => 'Un compte actif et une adresse e-mail vérifiée sont nécessaires pour publier un service.',
            ]);
        }

        if (in_array($profile->status, ['suspended', 'closed'], true)) {
            throw ValidationException::withMessages([
                'professional' => 'Un profil professionnel suspendu ou fermé ne peut pas publier de service.',
            ]);
        }

        if ($profile->verification_status !== ProfessionalVerificationStatus::VERIFIED || $profile->verified_at === null) {
            throw ValidationException::withMessages([
                'professional' => 'Le profil professionnel doit être vérifié avant la publication d’un service.',
            ]);
        }
    }

    public function assertAdminCanPublish(Service $service): void
    {
        $this->assertCommonRequirements($service);

        if ($service->professionalProfile?->isPubliclyVisible() !== true) {
            throw ValidationException::withMessages([
                'professional' => 'L’administration ne peut pas rendre visible un service dont le profil professionnel ne satisfait pas déjà les conditions de visibilité publique.',
            ]);
        }
    }

    public function assertSkillsCompatible(int $categoryId, array $skillIds): void
    {
        if ($skillIds === []) {
            return;
        }

        $category = Category::query()->with('parent')->findOrFail($categoryId);
        $skills = Skill::query()->with('category.parent')->whereIn('id', $skillIds)->get();

        if ($skills->count() !== count(array_unique($skillIds))) {
            throw ValidationException::withMessages([
                'skill_ids' => 'Une ou plusieurs compétences sélectionnées n’existent plus.',
            ]);
        }

        foreach ($skills as $skill) {
            if ($skill->status !== SkillStatus::ACTIVE || ! $this->isSkillCompatible($skill, $category)) {
                throw ValidationException::withMessages([
                    'skill_ids' => 'Chaque compétence doit être active et appartenir à la catégorie du service ou à son niveau parent/enfant.',
                ]);
            }
        }
    }

    private function assertCommonRequirements(Service $service): void
    {
        $service->loadMissing(['category', 'professionalProfile.user', 'skills.category.parent', 'images']);

        if ($service->status === ServiceStatus::ARCHIVED) {
            throw ValidationException::withMessages([
                'status' => 'Un service archivé ne peut pas être publié.',
            ]);
        }

        if ($service->moderation_status !== ServiceModerationStatus::CLEAR) {
            throw ValidationException::withMessages([
                'moderation' => 'Ce service est bloqué par le workflow de modération et ne peut pas être publié.',
            ]);
        }

        if ($service->category?->status !== CategoryStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'category_id' => 'La catégorie du service doit être active.',
            ]);
        }

        if ($service->professionalProfile === null || $service->professionalProfile->user === null) {
            throw ValidationException::withMessages([
                'professional' => 'Le service doit appartenir à un profil professionnel valide.',
            ]);
        }

        if (trim((string) $service->title) === '' || trim((string) $service->description) === '') {
            throw ValidationException::withMessages([
                'service' => 'Le service doit posséder un titre et une description.',
            ]);
        }

        $this->assertPricingIsCoherent($service);

        if ($service->images->where('is_cover', true)->isEmpty()) {
            throw ValidationException::withMessages([
                'images' => 'Une image de couverture est obligatoire pour publier le service.',
            ]);
        }

        $this->assertSkillsCompatible((int) $service->category_id, $service->skills->modelKeys());
    }

    private function assertPricingIsCoherent(Service $service): void
    {
        if (in_array($service->pricing_type, [ServicePricingType::FIXED, ServicePricingType::FROM], true)) {
            if ($service->price === null || $service->currency === null || $service->price_min !== null || $service->price_max !== null) {
                throw ValidationException::withMessages([
                    'pricing' => 'Le prix simple et la devise sont obligatoires ; les bornes ne sont pas autorisées.',
                ]);
            }
        }

        if ($service->pricing_type === ServicePricingType::RANGE) {
            if ($service->price_min === null || $service->price_max === null
                || $service->price_max < $service->price_min || $service->currency === null
                || $service->price !== null) {
                throw ValidationException::withMessages([
                    'pricing' => 'La fourchette de prix et la devise sont invalides.',
                ]);
            }
        }

        if ($service->pricing_type === ServicePricingType::QUOTE
            && ($service->price !== null || $service->price_min !== null || $service->price_max !== null)) {
            throw ValidationException::withMessages([
                'pricing' => 'Un service sur devis ne doit pas conserver de prix.',
            ]);
        }
    }

    private function isSkillCompatible(Skill $skill, Category $serviceCategory): bool
    {
        if ($skill->category_id === null || (int) $skill->category_id === (int) $serviceCategory->getKey()) {
            return true;
        }

        if ($serviceCategory->parent_id !== null && (int) $skill->category_id === (int) $serviceCategory->parent_id) {
            return true;
        }

        return $skill->category?->parent_id !== null
            && (int) $skill->category->parent_id === (int) $serviceCategory->getKey();
    }
}
