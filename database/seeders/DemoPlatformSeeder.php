<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deterministic, repeatable demonstration data for local development.
 *
 * All identities and payment references are fictitious. The payment provider
 * is deliberately "fake"; this seeder never calls a real payment service.
 */
class DemoPlatformSeeder extends Seeder
{
    private string $now;

    /** @var array<string, int> */
    private array $users = [];

    /** @var array<string, int> */
    private array $professionals = [];

    /** @var array<string, int> */
    private array $services = [];

    /** @var array<string, int> */
    private array $requests = [];

    /** @var array<string, int> */
    private array $quotations = [];

    /** @var array<string, int> */
    private array $offers = [];

    /** @var array<string, int> */
    private array $orders = [];

    /** @var array<string, int> */
    private array $payments = [];

    public function run(): void
    {
        $this->now = now()->toDateTimeString();

        $this->seedUsersAndAccess();
        $this->seedAddressesAndPreferences();
        $this->seedSkillsAndProfessionals();
        $this->seedServicesAndFavorites();
        $this->seedRequestsAndQuotations();
        $this->seedOrdersAndPayments();
        $this->seedWalletAndReviews();
        $this->seedMessagingNotificationsAndSupport();
        $this->seedModerationAndAudit();
    }

    /**
     * Insert or update by a stable business key, so running db:seed twice
     * does not create duplicate demo records.
     *
     * @param array<string, mixed> $keys
     * @param array<string, mixed> $values
     */
    private function put(string $table, array $keys, array $values, bool $timestamps = true): void
    {
        if ($timestamps) {
            $values['created_at'] ??= $this->now;
            $values['updated_at'] ??= $this->now;
        }

        DB::table($table)->updateOrInsert($keys, $values);
    }

    /**
     * @param array<string, mixed> $keys
     */
    private function id(string $table, array $keys): int
    {
        return (int) DB::table($table)->where($keys)->value('id');
    }

    private function seedUsersAndAccess(): void
    {
        $accounts = [
            'admin' => ['Aline Administratrice', 'admin@proxiwork.test', 'admin'],
            'moderator' => ['Patrick Modération', 'moderation@proxiwork.test', 'moderator'],
            'support' => ['Grâce Assistance', 'support@proxiwork.test', 'support'],
            'client_a' => ['Mireille Bahati', 'mireille.client@proxiwork.test', 'client'],
            'client_b' => ['David Kambale', 'david.client@proxiwork.test', 'client'],
            'pro_a' => ['Samuel Mugisha', 'samuel.dev@proxiwork.test', 'professional'],
            'pro_b' => ['Esther Kavira', 'esther.electricite@proxiwork.test', 'professional'],
            'pro_c' => ['Jean-Pierre Safari', 'jp.plomberie@proxiwork.test', 'professional'],
        ];

        foreach ($accounts as $key => [$name, $email, $roleName]) {
            $this->put('users', ['email' => $email], [
                'name' => $name,
                'email_verified_at' => $this->now,
                'password' => Hash::make('ProxiworkDemo!2026'),
                'account_status' => 'active',
            ]);

            $this->users[$key] = $this->id('users', ['email' => $email]);

            $this->put('profiles', ['user_id' => $this->users[$key]], [
                'first_name' => Str::before($name, ' '),
                'last_name' => Str::after($name, ' '),
                'phone' => match ($key) {
                    'client_a' => '+243970000101',
                    'client_b' => '+243970000102',
                    'pro_a' => '+243970000201',
                    'pro_b' => '+243970000202',
                    'pro_c' => '+243970000203',
                    'admin' => '+243970000301',
                    'moderator' => '+243970000302',
                    default => '+243970000303',
                },
                'bio' => match ($roleName) {
                    'professional' => 'Professionnel indépendant basé à Goma, RDC.',
                    'client' => 'Compte de démonstration client pour tester les demandes de services.',
                    default => 'Compte de démonstration interne PROXIWORK.',
                },
                'locale' => 'fr',
                'timezone' => 'Africa/Lubumbashi',
            ]);

            $this->put('notification_preferences', ['user_id' => $this->users[$key]], [
                'database_enabled' => true,
                'email_enabled' => true,
                'sms_enabled' => in_array($roleName, ['client', 'professional'], true),
                'push_enabled' => false,
            ]);

            $roleId = $this->id('roles', ['name' => $roleName]);
            if ($roleId > 0) {
                $this->put('role_user', [
                    'role_id' => $roleId,
                    'user_id' => $this->users[$key],
                ], []);
            }
        }
    }

    private function seedAddressesAndPreferences(): void
    {
        $addresses = [
            ['client_a', 'Domicile', 'Mireille Bahati', 'Goma', 'Karisimbi', 'Ndosho', 'Avenue de la Paix, près du marché', -1.6800, 29.2200],
            ['client_b', 'Bureau', 'David Kambale', 'Goma', 'Goma', 'Himbi', 'Boulevard Kanyamuhanga, bâtiment 12', -1.6750, 29.2350],
            ['pro_a', 'Atelier', 'Samuel Mugisha', 'Goma', 'Goma', 'Katindo', 'Avenue du Lac, porte bleue', -1.6850, 29.2300],
            ['pro_b', 'Domicile', 'Esther Kavira', 'Goma', 'Goma', 'Les Volcans', 'Rue des Écoles, parcelle 18', -1.6700, 29.2250],
        ];

        foreach ($addresses as [$userKey, $label, $recipient, $city, $commune, $neighborhood, $line, $lat, $lng]) {
            $this->put('addresses', [
                'user_id' => $this->users[$userKey],
                'label' => $label,
            ], [
                'recipient_name' => $recipient,
                'contact_phone' => '+243970000101',
                'country_code' => 'CD',
                'province' => 'Nord-Kivu',
                'city' => $city,
                'commune' => $commune,
                'neighborhood' => $neighborhood,
                'address_line_1' => $line,
                'address_line_2' => null,
                'landmark' => 'Adresse fictive de démonstration',
                'postal_code' => null,
                'latitude' => $lat,
                'longitude' => $lng,
                'is_default' => true,
            ]);
        }
    }

    private function seedSkillsAndProfessionals(): void
    {
        $skills = [
            'laravel' => ['Laravel / PHP', 'Développement backend et API REST.'],
            'flutter' => ['Flutter / Dart', 'Applications mobiles multiplateformes.'],
            'networking' => ['Réseaux informatiques', 'Configuration et dépannage réseau.'],
            'electricity' => ['Installation électrique', 'Installation et maintenance électrique résidentielle.'],
            'plumbing' => ['Plomberie sanitaire', 'Dépannage et installation sanitaire.'],
            'uiux' => ['UI/UX Design', 'Conception d’interfaces web et mobiles.'],
            'database' => ['MySQL', 'Modélisation, optimisation et maintenance de bases de données.'],
        ];

        foreach ($skills as $slug => [$name, $description]) {
            $this->put('skills', ['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'icon' => null,
                'status' => 'active',
                'sort_order' => count($this->services) + count($skills),
            ]);
        }

        $profiles = [
            'pro_a' => [
                'title' => 'Développeur web et mobile',
                'description' => 'Conçoit des sites vitrines, applications métier et API sécurisées pour les PME et associations.',
                'years' => 5,
                'price' => 45,
                'skills' => ['laravel', 'flutter', 'database', 'uiux'],
            ],
            'pro_b' => [
                'title' => 'Électricienne bâtiment',
                'description' => 'Installation, diagnostic et mise en sécurité des circuits électriques résidentiels.',
                'years' => 6,
                'price' => 25,
                'skills' => ['electricity'],
            ],
            'pro_c' => [
                'title' => 'Plombier sanitaire',
                'description' => 'Recherche de fuites, installation de robinets, sanitaires et petits réseaux d’eau.',
                'years' => 8,
                'price' => 20,
                'skills' => ['plumbing'],
            ],
        ];

        foreach ($profiles as $userKey => $profile) {
            $this->put('professional_profiles', ['user_id' => $this->users[$userKey]], [
                'professional_title' => $profile['title'],
                'verification_status' => 'verified',
                'availability_status' => 'available',
                'rating_average' => $userKey === 'pro_a' ? 4.90 : 4.70,
                'rating_count' => $userKey === 'pro_a' ? 12 : 7,
                'description' => $profile['description'],
                'years_experience' => $profile['years'],
                'starting_price' => $profile['price'],
                'currency' => 'USD',
                'province' => 'Nord-Kivu',
                'city' => 'Goma',
                'commune' => $userKey === 'pro_b' ? 'Goma' : 'Karisimbi',
                'service_radius_km' => 15,
                'latitude' => -1.6800,
                'longitude' => 29.2300,
                'status' => 'active',
                'visibility' => 'public',
                'verified_at' => now()->subDays(30)->toDateTimeString(),
            ]);

            $this->professionals[$userKey] = $this->id('professional_profiles', [
                'user_id' => $this->users[$userKey],
            ]);

            foreach ($profile['skills'] as $skillSlug) {
                $skillId = $this->id('skills', ['slug' => $skillSlug]);
                $this->put('professional_skills', [
                    'professional_profile_id' => $this->professionals[$userKey],
                    'skill_id' => $skillId,
                ], [
                    'proficiency_level' => 'advanced',
                    'years_experience' => $profile['years'],
                ]);
            }

            $adminId = $this->users['admin'];
            $this->put('professional_verification_reviews', [
                'professional_profile_id' => $this->professionals[$userKey],
                'admin_user_id' => $adminId,
                'to_status' => 'verified',
            ], [
                'from_status' => 'under_review',
                'reason_code' => 'documents_approved',
                'note' => 'Dossier fictif validé pour les essais locaux.',
            ]);
        }
    }

    private function seedServicesAndFavorites(): void
    {
        $webCategory = $this->id('categories', ['slug' => 'developpement-web']);
        $mobileCategory = $this->id('categories', ['slug' => 'developpement-mobile']);
        $electricCategory = $this->id('categories', ['slug' => 'electricite']);
        $plumbingCategory = $this->id('categories', ['slug' => 'plomberie']);

        $services = [
            'site-pme' => ['pro_a', $webCategory, 'Création de site web pour PME', 'Site web responsive avec pages de présentation, formulaire de contact et accompagnement à la mise en ligne.', 350, 'fixed', 3],
            'api-metier' => ['pro_a', $webCategory, 'Développement d’une API métier', 'Conception d’une API REST documentée avec authentification, validation et gestion des erreurs.', 500, 'from', 5],
            'application-mobile' => ['pro_a', $mobileCategory, 'Prototype d’application mobile', 'Prototype Flutter avec navigation, formulaires et intégration API.', 450, 'quote', 4],
            'installation-electrique' => ['pro_b', $electricCategory, 'Diagnostic électrique résidentiel', 'Contrôle des prises, protections et points lumineux avec compte rendu des interventions nécessaires.', 35, 'fixed', 2],
            'reparation-fuite' => ['pro_c', $plumbingCategory, 'Recherche et réparation de fuite', 'Diagnostic d’une fuite visible et réparation simple après validation du devis.', 25, 'from', 1],
        ];

        foreach ($services as $slug => [$professionalKey, $categoryId, $title, $description, $price, $pricingType, $duration]) {
            if ($categoryId < 1) {
                continue;
            }

            $this->put('services', ['slug' => $slug], [
                'professional_profile_id' => $this->professionals[$professionalKey],
                'category_id' => $categoryId,
                'title' => $title,
                'short_description' => Str::limit($description, 250),
                'description' => $description,
                'pricing_type' => $pricingType,
                'price' => $pricingType === 'quote' ? null : $price,
                'price_min' => $pricingType === 'from' ? $price : null,
                'price_max' => null,
                'currency' => 'USD',
                'estimated_duration_minutes' => $duration * 60,
                'status' => 'published',
                'sort_order' => count($this->services) + 1,
                'published_at' => now()->subDays(10)->toDateTimeString(),
            ]);

            $this->services[$slug] = $this->id('services', ['slug' => $slug]);

            $this->put('service_images', [
                'service_id' => $this->services[$slug],
                'path' => 'demo/services/' . $slug . '.jpg',
            ], [
                'alt_text' => $title . ' — image de démonstration',
                'sort_order' => 0,
                'is_cover' => true,
            ]);

            $professionalSkills = DB::table('professional_skills')
                ->where('professional_profile_id', $this->professionals[$professionalKey])
                ->pluck('skill_id');

            foreach ($professionalSkills as $skillId) {
                $this->put('service_skills', [
                    'service_id' => $this->services[$slug],
                    'skill_id' => $skillId,
                ], []);
            }
        }

        foreach ([
            ['client_a', 'pro_a'],
            ['client_a', 'pro_b'],
            ['client_b', 'pro_a'],
        ] as [$clientKey, $professionalKey]) {
            $this->put('favorites', [
                'user_id' => $this->users[$clientKey],
                'professional_profile_id' => $this->professionals[$professionalKey],
            ], []);
        }
    }

    private function seedRequestsAndQuotations(): void
    {
        $requestRows = [
            'web_project' => [
                'client_a', 'pro_a', 'site-pme', 'Projet de site pour une petite entreprise',
                'Créer un site vitrine rapide et adapté aux téléphones, avec une page services et un formulaire de contact.',
                300, 450, 'accepted',
            ],
            'electric_check' => [
                'client_b', 'pro_b', 'installation-electrique', 'Vérification électrique d’un logement',
                'Vérifier plusieurs prises et deux points lumineux avant l’installation de nouveaux appareils.',
                25, 60, 'accepted',
            ],
            'plumbing_question' => [
                'client_a', 'pro_c', 'reparation-fuite', 'Fuite sous évier de cuisine',
                'Une fuite apparaît sous l’évier après utilisation. Demande de diagnostic et de devis.',
                15, 40, 'requested',
            ],
        ];

        foreach ($requestRows as $key => [$clientKey, $professionalKey, $serviceSlug, $title, $description, $min, $max, $status]) {
            $addressId = DB::table('addresses')->where('user_id', $this->users[$clientKey])->value('id');
            $this->put('service_requests', [
                'client_id' => $this->users[$clientKey],
                'service_id' => $this->services[$serviceSlug],
                'title' => $title,
            ], [
                'professional_id' => $this->professionals[$professionalKey],
                'address_id' => $addressId,
                'description' => $description,
                'budget_min' => $min,
                'budget_max' => $max,
                'currency' => 'USD',
                'desired_at' => now()->addDays(4)->toDateTimeString(),
                'status' => $status,
                'requested_at' => now()->subDays(5)->toDateTimeString(),
            ]);

            $this->requests[$key] = $this->id('service_requests', [
                'client_id' => $this->users[$clientKey],
                'service_id' => $this->services[$serviceSlug],
                'title' => $title,
            ]);

            $history = $status === 'accepted'
                ? [['requested', 'Demande créée'], ['quoted', 'Devis transmis'], ['accepted', 'Devis accepté']]
                : [[null, 'Demande créée']];

            foreach ($history as [$toStatus, $reason]) {
                $toStatus ??= 'requested';
                $this->put('service_request_status_histories', [
                    'service_request_id' => $this->requests[$key],
                    'to_status' => $toStatus,
                ], [
                    'from_status' => null,
                    'changed_by' => $this->users[$clientKey],
                    'reason' => $reason,
                    'created_at' => now()->subDays(4)->toDateTimeString(),
                ], false);
            }

            if ($key === 'plumbing_question') {
                continue;
            }

            $this->put('quotations', ['service_request_id' => $this->requests[$key]], [
                'status' => 'accepted',
                'accepted_at' => now()->subDays(2)->toDateTimeString(),
                'rejected_at' => null,
                'withdrawn_at' => null,
            ]);

            $this->quotations[$key] = $this->id('quotations', [
                'service_request_id' => $this->requests[$key],
            ]);

            $amount = $key === 'web_project' ? 380 : 45;
            $this->put('quotation_offers', [
                'quotation_id' => $this->quotations[$key],
                'version' => 1,
            ], [
                'created_by' => $this->users[$professionalKey],
                'actor_type' => 'professional',
                'amount' => $amount,
                'currency' => 'USD',
                'description' => $key === 'web_project'
                    ? 'Site vitrine de cinq pages, version mobile, formulaire de contact et séance de prise en main.'
                    : 'Diagnostic, contrôle des points concernés et rapport avec recommandations.',
                'duration_value' => $key === 'web_project' ? 7 : 1,
                'duration_unit' => 'days',
                'conditions' => 'Tout travail supplémentaire fait l’objet d’un accord préalable.',
                'valid_until' => now()->addDays(14)->toDateTimeString(),
            ], false);

            $this->offers[$key] = (int) DB::table('quotation_offers')
                ->where('quotation_id', $this->quotations[$key])
                ->where('version', 1)
                ->value('id');

            $this->put('quotations', ['id' => $this->quotations[$key]], [
                'current_offer_id' => $this->offers[$key],
                'accepted_offer_id' => $this->offers[$key],
            ]);

            $this->put('quotation_events', [
                'quotation_id' => $this->quotations[$key],
                'type' => 'offer_accepted',
            ], [
                'actor_id' => $this->users[$clientKey],
                'offer_id' => $this->offers[$key],
                'metadata' => json_encode(['source' => 'demo_seeder'], JSON_THROW_ON_ERROR),
                'created_at' => now()->subDays(2)->toDateTimeString(),
            ], false);
        }
    }

    private function seedOrdersAndPayments(): void
    {
        foreach ([
            'web_project' => ['client_a', 'pro_a', 380, 'completed', 'succeeded'],
            'electric_check' => ['client_b', 'pro_b', 45, 'pending_payment', 'pending'],
        ] as $key => [$clientKey, $professionalKey, $amount, $orderStatus, $paymentStatus]) {
            $requestId = $this->requests[$key];
            $quotationId = $this->quotations[$key];
            $offerId = $this->offers[$key];

            $this->put('orders', ['service_request_id' => $requestId], [
                'order_number' => 'PXW-DEMO-' . strtoupper(substr($key, 0, 3)) . '-' . str_pad((string) $requestId, 5, '0', STR_PAD_LEFT),
                'quotation_id' => $quotationId,
                'accepted_offer_id' => $offerId,
                'client_id' => $this->users[$clientKey],
                'professional_id' => $this->professionals[$professionalKey],
                'status' => $orderStatus,
                'currency' => 'USD',
                'subtotal' => $amount,
                'total' => $amount,
                'accepted_at' => now()->subDays(3)->toDateTimeString(),
                'confirmed_at' => $orderStatus === 'completed' ? now()->subDays(2)->toDateTimeString() : null,
                'started_at' => $orderStatus === 'completed' ? now()->subDays(1)->toDateTimeString() : null,
                'completed_at' => $orderStatus === 'completed' ? now()->subHours(5)->toDateTimeString() : null,
                'cancelled_at' => null,
            ]);

            $this->orders[$key] = $this->id('orders', ['service_request_id' => $requestId]);

            $this->put('order_items', [
                'order_id' => $this->orders[$key],
                'service_title' => $key === 'web_project' ? 'Création de site web pour PME' : 'Diagnostic électrique résidentiel',
            ], [
                'service_id' => $this->services[$key === 'web_project' ? 'site-pme' : 'installation-electrique'],
                'service_description' => 'Ligne de commande de démonstration PROXIWORK.',
                'quantity' => 1,
                'unit_price' => $amount,
                'subtotal' => $amount,
                'currency' => 'USD',
                'duration_value' => $key === 'web_project' ? 7 : 1,
                'duration_unit' => 'days',
                'conditions' => 'Données fictives destinées au développement local.',
            ]);

            $this->put('order_address_snapshots', ['order_id' => $this->orders[$key]], [
                'recipient_name' => $key === 'web_project' ? 'Mireille Bahati' : 'David Kambale',
                'contact_phone' => '+243970000101',
                'country_code' => 'CD',
                'province' => 'Nord-Kivu',
                'city' => 'Goma',
                'commune' => 'Goma',
                'neighborhood' => $key === 'web_project' ? 'Ndosho' : 'Himbi',
                'address_line_1' => 'Adresse fictive de démonstration',
                'address_line_2' => null,
                'landmark' => 'Ne pas utiliser pour une livraison réelle',
                'postal_code' => null,
                'latitude' => -1.6800,
                'longitude' => 29.2300,
            ]);

            $this->put('order_status_histories', [
                'order_id' => $this->orders[$key],
                'to_status' => $orderStatus,
            ], [
                'from_status' => null,
                'changed_by' => $this->users[$clientKey],
                'reason' => 'État de démonstration pour les tests locaux.',
                'metadata' => json_encode(['source' => 'demo_seeder'], JSON_THROW_ON_ERROR),
                'created_at' => now()->subHours(5)->toDateTimeString(),
            ], false);

            $intentKey = 'demo-intent-' . $key;
            $this->put('payment_intents', [
                'client_id' => $this->users[$clientKey],
                'idempotency_key' => $intentKey,
            ], [
                'order_id' => $this->orders[$key],
                'request_fingerprint' => hash('sha256', $intentKey),
                'method' => 'mobile_money',
                'provider' => 'fake',
                'status' => $paymentStatus,
                'currency' => 'USD',
                'amount' => $amount,
                'provider_reference' => 'DEMO-REF-' . strtoupper($key),
                'redirect_url' => null,
                'instructions' => 'Simulation locale uniquement : aucun paiement réel ne sera déclenché.',
                'failure_code' => null,
                'failure_message' => null,
                'metadata' => json_encode(['environment' => 'local', 'sandbox' => true], JSON_THROW_ON_ERROR),
            ]);

            $intentId = $this->id('payment_intents', [
                'client_id' => $this->users[$clientKey],
                'idempotency_key' => $intentKey,
            ]);

            $this->put('payments', ['order_id' => $this->orders[$key]], [
                'client_id' => $this->users[$clientKey],
                'payment_intent_id' => $intentId,
                'method' => 'mobile_money',
                'provider' => 'fake',
                'status' => $paymentStatus,
                'currency' => 'USD',
                'amount' => $amount,
                'paid_at' => $paymentStatus === 'succeeded' ? now()->subHours(6)->toDateTimeString() : null,
                'failed_at' => null,
                'cancelled_at' => null,
            ]);

            $this->payments[$key] = $this->id('payments', ['order_id' => $this->orders[$key]]);

            $this->put('payment_transactions', [
                'payment_id' => $this->payments[$key],
                'idempotency_key' => 'demo-tx-' . $key,
            ], [
                'provider' => 'fake',
                'provider_transaction_id' => 'DEMO-TX-' . strtoupper($key),
                'provider_reference' => 'DEMO-REF-' . strtoupper($key),
                'provider_event_id' => 'DEMO-EVENT-' . strtoupper($key),
                'status' => $paymentStatus,
                'currency' => 'USD',
                'amount' => $amount,
                'failure_code' => null,
                'failure_message' => null,
                'request_metadata' => json_encode(['sandbox' => true], JSON_THROW_ON_ERROR),
                'response_metadata' => json_encode(['simulated' => true], JSON_THROW_ON_ERROR),
                'initiated_at' => now()->subDays(3)->toDateTimeString(),
                'processing_at' => $paymentStatus === 'pending' ? now()->subDays(3)->toDateTimeString() : null,
                'processed_at' => $paymentStatus === 'succeeded' ? now()->subHours(6)->toDateTimeString() : null,
                'failed_at' => null,
            ]);
        }
    }

    private function seedWalletAndReviews(): void
    {
        $professionalId = $this->professionals['pro_a'];
        $gross = 380.00;
        $commission = 38.00;
        $net = 342.00;

        $this->put('commissions', ['order_id' => $this->orders['web_project']], [
            'payment_id' => $this->payments['web_project'],
            'professional_id' => $professionalId,
            'gross_amount' => $gross,
            'commission_rate' => 10,
            'commission_amount' => $commission,
            'net_amount' => $net,
            'currency' => 'USD',
            'calculation_type' => 'percentage',
            'status' => 'posted',
            'posted_at' => now()->subHours(6)->toDateTimeString(),
            'reversed_at' => null,
            'reversal_of_id' => null,
        ]);

        $this->put('wallets', [
            'professional_id' => $professionalId,
            'currency' => 'USD',
        ], [
            'available_balance' => $net,
            'pending_balance' => 0,
            'locked_balance' => 0,
            'status' => 'active',
        ]);

        $walletId = $this->id('wallets', [
            'professional_id' => $professionalId,
            'currency' => 'USD',
        ]);

        $this->put('wallet_transactions', [
            'wallet_id' => $walletId,
            'idempotency_key' => 'demo-commission-web-project',
        ], [
            'type' => 'commission_credit',
            'direction' => 'credit',
            'currency' => 'USD',
            'amount' => $net,
            'balance_before' => 0,
            'balance_after' => $net,
            'reference_type' => 'order',
            'reference_id' => $this->orders['web_project'],
            'description' => 'Revenu net simulé après commission de la plateforme.',
            'metadata' => json_encode(['gross' => $gross, 'commission' => $commission], JSON_THROW_ON_ERROR),
        ]);

        $this->put('withdrawals', [
            'professional_id' => $professionalId,
            'idempotency_key' => 'demo-withdrawal-pro-a',
        ], [
            'wallet_id' => $walletId,
            'provider' => 'fake',
            'destination' => '+243970000201',
            'status' => 'requested',
            'currency' => 'USD',
            'amount' => 50,
            'provider_transaction_id' => null,
            'failure_code' => null,
            'failure_message' => null,
            'requested_at' => now()->subHours(2)->toDateTimeString(),
            'processing_at' => null,
            'completed_at' => null,
        ]);

        $this->put('reviews', ['order_id' => $this->orders['web_project']], [
            'client_id' => $this->users['client_a'],
            'professional_id' => $professionalId,
            'rating' => 5,
            'comment' => 'Communication claire, travail livré et explications faciles à suivre.',
            'status' => 'published',
            'published_at' => now()->subHours(4)->toDateTimeString(),
            'moderated_at' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
        ]);

        $reviewId = $this->id('reviews', ['order_id' => $this->orders['web_project']]);
        $this->put('review_responses', ['review_id' => $reviewId], [
            'professional_id' => $professionalId,
            'response' => 'Merci pour votre confiance. Je reste disponible pour les ajustements convenus.',
            'status' => 'published',
            'moderated_at' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
        ]);
    }

    private function seedMessagingNotificationsAndSupport(): void
    {
        foreach ([
            ['client_a', 'pro_a', 'web_project', 'Merci, pouvez-vous aussi expliquer comment modifier les textes ?'],
            ['client_b', 'pro_b', 'electric_check', 'Je suis disponible jeudi après-midi pour le contrôle.'],
        ] as [$clientKey, $professionalKey, $requestKey, $messageBody]) {
            $this->put('conversations', [
                'type' => 'client_professional',
                'client_id' => $this->users[$clientKey],
                'professional_id' => $this->professionals[$professionalKey],
            ], [
                'status' => 'open',
                'service_request_id' => $this->requests[$requestKey],
                'order_id' => $this->orders[$requestKey] ?? null,
            ]);

            $conversationId = $this->id('conversations', [
                'type' => 'client_professional',
                'client_id' => $this->users[$clientKey],
                'professional_id' => $this->professionals[$professionalKey],
            ]);

            $professionalUserId = (int) DB::table('professional_profiles')
                ->where('id', $this->professionals[$professionalKey])
                ->value('user_id');

            foreach ([$this->users[$clientKey], $professionalUserId] as $participantId) {
                $this->put('conversation_participants', [
                    'conversation_id' => $conversationId,
                    'user_id' => $participantId,
                ], [
                    'last_read_message_id' => null,
                    'joined_at' => now()->subDays(2)->toDateTimeString(),
                    'left_at' => null,
                ]);
            }

            $this->put('messages', [
                'conversation_id' => $conversationId,
                'sender_id' => $this->users[$clientKey],
                'body' => $messageBody,
            ], []);

            $messageId = (int) DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->where('sender_id', $this->users[$clientKey])
                ->where('body', $messageBody)
                ->value('id');

            $this->put('conversation_participants', [
                'conversation_id' => $conversationId,
                'user_id' => $this->users[$clientKey],
            ], [
                'last_read_message_id' => $messageId,
                'joined_at' => now()->subDays(2)->toDateTimeString(),
                'left_at' => null,
            ]);
        }

        foreach ([
            ['client_a', 'App\\Notifications\\DemoServiceUpdate', 'Votre demande de service a été mise à jour.'],
            ['pro_a', 'App\\Notifications\\DemoPaymentReceived', 'Un paiement simulé a été enregistré dans votre tableau de bord.'],
            ['client_b', 'App\\Notifications\\DemoQuoteReceived', 'Un devis de démonstration est disponible.'],
        ] as $index => [$userKey, $type, $message]) {
            $uuid = sprintf('00000000-0000-4000-8000-%012d', $index + 1);
            $this->put('notifications', ['id' => $uuid], [
                'type' => $type,
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $this->users[$userKey],
                'data' => json_encode([
                    'title' => 'Notification de démonstration',
                    'message' => $message,
                    'url' => '/',
                    'demo' => true,
                ], JSON_THROW_ON_ERROR),
                'read_at' => $index === 0 ? now()->subHours(1)->toDateTimeString() : null,
            ]);
        }

        $this->put('support_tickets', [
            'user_id' => $this->users['client_b'],
            'subject' => 'Question sur le suivi d’une demande',
        ], [
            'assigned_to' => $this->users['support'],
            'category' => 'ACCOUNT',
            'priority' => 'normal',
            'status' => 'in_progress',
            'last_message_at' => now()->subHours(1)->toDateTimeString(),
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        $ticketId = $this->id('support_tickets', [
            'user_id' => $this->users['client_b'],
            'subject' => 'Question sur le suivi d’une demande',
        ]);

        $this->put('ticket_messages', [
            'ticket_id' => $ticketId,
            'sender_id' => $this->users['client_b'],
            'body' => 'Bonjour, où puis-je consulter les étapes de ma demande ?',
        ], []);

        $this->put('ticket_messages', [
            'ticket_id' => $ticketId,
            'sender_id' => $this->users['support'],
            'body' => 'Bonjour, ouvrez votre tableau de bord puis la rubrique Mes demandes.',
        ], []);
    }

    private function seedModerationAndAudit(): void
    {
        $serviceId = $this->services['site-pme'];

        $this->put('reports', [
            'reporter_id' => $this->users['client_b'],
            'target_type' => 'service',
            'target_id' => $serviceId,
            'reason_code' => 'incorrect_information',
        ], [
            'description' => 'Signalement fictif créé pour tester la file de modération.',
            'status' => 'under_review',
            'priority' => 'normal',
            'assigned_to' => $this->users['moderator'],
            'resolved_by' => null,
            'resolved_at' => null,
            'resolution_note' => null,
        ]);

        $this->put('reports', [
            'reporter_id' => $this->users['client_a'],
            'target_type' => 'service',
            'target_id' => $serviceId,
            'reason_code' => 'duplicate_listing',
        ], [
            'description' => 'Signalement résolu fictif pour vérifier les historiques de modération.',
            'status' => 'resolved',
            'priority' => 'low',
            'assigned_to' => $this->users['moderator'],
            'resolved_by' => $this->users['moderator'],
            'resolved_at' => now()->subDays(2)->toDateTimeString(),
            'resolution_note' => 'Vérification terminée ; le contenu de démonstration est conservé.',
        ]);

        $resolvedReportId = $this->id('reports', [
            'reporter_id' => $this->users['client_a'],
            'target_type' => 'service',
            'target_id' => $serviceId,
            'reason_code' => 'duplicate_listing',
        ]);

        $this->put('moderation_actions', [
            'report_id' => $resolvedReportId,
            'action_type' => 'warning',
        ], [
            'moderator_id' => $this->users['moderator'],
            'reason_code' => 'duplicate_listing',
            'note' => 'Action de démonstration sans effet sur un compte réel.',
            'target_type' => 'service',
            'target_id' => $serviceId,
        ]);

        $this->put('audit_logs', [
            'user_id' => $this->users['admin'],
            'action' => 'demo.seeded',
            'subject_type' => 'service',
            'subject_id' => $serviceId,
        ], [
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PROXIWORK local demo seeder',
            'metadata' => json_encode(['environment' => 'local', 'demo' => true], JSON_THROW_ON_ERROR),
        ]);
    }
}
