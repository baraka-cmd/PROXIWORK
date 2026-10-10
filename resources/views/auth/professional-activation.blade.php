@extends('layouts.auth')

@section('title', 'Ajouter un espace professionnel — PROXIWORK')
@section('meta_description', 'Ajoutez un espace professionnel à votre compte PROXIWORK existant.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
<div class="register-page" data-registration-page>
    <section class="register-card" aria-labelledby="register-title">
        <header class="register-card__header">
            <span class="register-eyebrow"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> ESPACE PROFESSIONNEL</span>
            <h1 id="register-title">Devenir professionnel</h1>
            <p>Ajoutez un espace professionnel à votre compte existant. Votre identité, vos commandes, vos messages et votre historique client seront conservés.</p>
        </header>

        @if ($errors->any())
            <div class="register-alert" role="alert" tabindex="-1">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div><strong>Vérifiez les informations saisies</strong><p>{{ $errors->first() }}</p></div>
            </div>
        @endif

        <form method="POST" action="{{ route('account.professional-profile.store') }}" class="register-form" data-register-form enctype="multipart/form-data" novalidate>
            @csrf

            <input type="radio" name="account_type" value="professional" checked hidden aria-hidden="true">



            <section class="register-step is-active" data-registration-step="1" aria-labelledby="identity-title">
                <div class="register-step-heading">
                    <span class="register-step-number">01</span>
                    <div><h2 id="identity-title">Votre identité et votre activité</h2><p>Votre compte existant est conservé. Renseignez les informations nécessaires à votre espace professionnel.</p></div>
                </div>

                <div class="register-fields-grid">
                    <input type="hidden" id="first_name" name="first_name" value="{{ old('first_name', $user->profile?->first_name ?? $user->name) }}">
                    <input type="hidden" id="last_name" name="last_name" value="{{ old('last_name', $user->profile?->last_name ?? '') }}">
                    <input type="hidden" id="email" name="email" value="{{ old('email', $user->email) }}">
                    <input type="hidden" id="phone" name="phone" value="{{ old('phone', $user->profile?->phone ?? '') }}">

                    <div class="register-field register-field--full">
                        <label>Compte existant</label>
                        <p><strong>{{ $user->name }}</strong> · {{ $user->email }}</p>
                        <small class="register-hint">Cette identité sera réutilisée. Aucun deuxième compte ne sera créé.</small>
                    </div>

                    <div class="register-field register-field--full register-terms">
                        <label><input type="checkbox" name="terms" value="1" @checked(old('terms')) required> Je confirme accepter les conditions d’utilisation et la politique de confidentialité pour mon activité professionnelle.</label>
                        @error('terms')<p class="register-field-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="register-professional-only" data-professional-step-fields>
                    <div class="register-step-heading">
                        <span class="register-step-number">01</span>
                        <div><h2>Activité professionnelle</h2><p>Les informations détaillées pourront être complétées plus tard.</p></div>
                    </div>
                    <div class="register-fields-grid">
                        <div class="register-field">
                            <label for="business_name">Nom commercial <span class="register-optional">(facultatif)</span></label>
                            <input class="register-text-input" id="business_name" name="business_name" value="{{ old('business_name') }}" maxlength="160" data-professional-required>
                            @error('business_name')<p class="register-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="register-field">
                            <label for="city">Ville d’intervention</label>
                            <input class="register-text-input" id="city" name="city" value="{{ old('city') }}" maxlength="100" placeholder="Ex. Goma" data-professional-required>
                            @error('city')<p class="register-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="register-field">
                            <label for="province">Province <span class="register-optional">(facultatif)</span></label>
                            <input class="register-text-input" id="province" name="province" value="{{ old('province') }}" maxlength="100" placeholder="Ex. Nord-Kivu">
                        </div>
                        <div class="register-field">
                            <label for="commune">Commune / quartier <span class="register-optional">(facultatif)</span></label>
                            <input class="register-text-input" id="commune" name="commune" value="{{ old('commune') }}" maxlength="100">
                        </div>
                    </div>
                </div>

                <div class="register-actions">
                    <button type="button" class="register-submit" data-registration-next>Continuer <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    <button type="submit" class="register-submit" data-client-submit><span>Créer mon compte client</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </section>

            <div class="register-professional-wizard" data-professional-wizard>
                <nav class="register-progress" aria-label="Étapes d’activation professionnelle">
                    <span data-progress-step="1" class="is-current"><b>1</b> Activité</span>
                    <span data-progress-step="2"><b>2</b> Compétences</span>
                    <span data-progress-step="3"><b>3</b> Services</span>
                    <span data-progress-step="4"><b>4</b> Preuves</span>
                    <span data-progress-step="5"><b>5</b> Récapitulatif</span>
                </nav>

                <section class="register-step" data-registration-step="2" aria-labelledby="skills-title">
                    <div class="register-step-heading"><span class="register-step-number">02</span><div><h2 id="skills-title">Catégories et compétences</h2><p>Choisissez une ou deux catégories principales, puis les compétences associées.</p></div></div>
                    <div class="register-field">
                        <span class="register-label">Catégories (2 maximum)</span>
                        <div class="register-choice-list" data-category-list>
                            @forelse ($categories as $category)
                                <label class="register-check-card">
                                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" data-category-choice data-category-id="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', [])))>
                                    <span><strong>{{ $category->name }}</strong>@if ($category->description)<small>{{ $category->description }}</small>@endif</span>
                                </label>
                            @empty
                                <p class="register-hint">Aucune catégorie active n’est configurée pour le moment. L’administration doit créer et activer les catégories avant l’inscription des professionnels.</p>
                            @endforelse
                        </div>
                        @error('category_ids')<p class="register-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="register-field">
                        <label for="skill-search">Compétences</label>
                        <input class="register-text-input" id="skill-search" type="search" placeholder="Rechercher une compétence…" data-skill-search>
                        <div class="register-choice-list" data-skill-list>
                            @foreach ($categories as $category)
                                @foreach ($category->skills as $skill)
                                    <label class="register-check-card" data-skill-option data-category-id="{{ $category->id }}" data-skill-name="{{ mb_strtolower($skill->name) }}">
                                        <input type="checkbox" name="skill_ids[]" value="{{ $skill->id }}" data-skill-choice @checked(in_array($skill->id, old('skill_ids', [])))>
                                        <span><strong>{{ $skill->name }}</strong><small>{{ $category->name }}</small></span>
                                    </label>
                                @endforeach
                            @endforeach
                        </div>
                        <small class="register-hint">Seules les compétences actives liées aux catégories sélectionnées sont proposées. Une compétence désactivée est refusée côté serveur.</small>
                        @error('skill_ids')<p class="register-field-error">{{ $message }}</p>@enderror
                    </div>
                </section>

                <section class="register-step" data-registration-step="3" aria-labelledby="services-title">
                    <div class="register-step-heading"><span class="register-step-number">03</span><div><h2 id="services-title">Services proposés</h2><p>Un service est une prestation concrète, distincte d’une catégorie ou d’une compétence. Les services seront enregistrés comme brouillons.</p></div></div>
                    <div class="register-services" data-services-container>
                        <template data-service-template>
                            <article class="register-service-card" data-service-card>
                                <div class="register-service-card__heading"><h3>Service <span data-service-number></span></h3><button type="button" class="register-remove-button" data-remove-service>Retirer</button></div>
                                <div class="register-fields-grid">
                                    <div class="register-field">
                                        <label>Catégorie du service</label>
                                        <select class="register-select-input" name="services[__INDEX__][category_id]" data-service-category required>
                                            <option value="">Choisissez une catégorie</option>
                                            @foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="register-field register-field--full">
                                        <span class="register-label">Compétences utilisées</span>
                                        <div class="register-choice-list" data-service-skills>
                                            @foreach ($categories as $category)
                                                @foreach ($category->skills as $skill)
                                                    <label class="register-check-card" data-service-skill-option data-category-id="{{ $category->id }}" data-skill-id="{{ $skill->id }}">
                                                        <input type="checkbox" name="services[__INDEX__][skill_ids][]" value="{{ $skill->id }}" disabled>
                                                        <span><strong>{{ $skill->name }}</strong><small>{{ $category->name }}</small></span>
                                                    </label>
                                                @endforeach
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="register-field register-field--full"><label>Nom du service</label><input class="register-text-input" name="services[__INDEX__][title]" maxlength="160" required placeholder="Ex. Installation d’un lavabo"></div>
                                    <div class="register-field register-field--full"><label>Description détaillée</label><textarea class="register-textarea" name="services[__INDEX__][description]" minlength="10" maxlength="5000" rows="4" required></textarea></div>
                                    <div class="register-field"><label>Mode de tarification</label><select class="register-select-input" name="services[__INDEX__][pricing_type]" data-pricing-type required><option value="quote">Sur devis</option><option value="fixed">Prix fixe</option><option value="from">À partir de</option><option value="range">Fourchette</option></select></div>
                                    <div class="register-field"><label>Devise</label><select class="register-select-input" name="services[__INDEX__][currency]"><option value="CDF">CDF</option><option value="USD">USD</option></select></div>
                                    <div class="register-field" data-price-field><label>Prix</label><input class="register-text-input" name="services[__INDEX__][price]" type="number" min="0" step="0.01"></div>
                                    <div class="register-field" data-price-min-field><label>Prix minimum</label><input class="register-text-input" name="services[__INDEX__][price_min]" type="number" min="0" step="0.01"></div>
                                    <div class="register-field" data-price-max-field><label>Prix maximum</label><input class="register-text-input" name="services[__INDEX__][price_max]" type="number" min="0" step="0.01"></div>
                                    <div class="register-field"><label>Unité de facturation</label><input class="register-text-input" name="services[__INDEX__][billing_unit]" maxlength="50" placeholder="Ex. par intervention"></div>
                                    <div class="register-field"><label>Durée estimée (minutes)</label><input class="register-text-input" name="services[__INDEX__][estimated_duration_minutes]" type="number" min="1" max="10080"></div>
                                    <div class="register-field register-field--full"><label>Zone d’intervention</label><input class="register-text-input" name="services[__INDEX__][service_area]" maxlength="255" placeholder="Ex. Goma et environs"></div>
                                    <div class="register-field register-field--full"><label>Conditions particulières</label><textarea class="register-textarea" name="services[__INDEX__][conditions]" rows="2" maxlength="3000"></textarea></div>
                                    <div class="register-field register-field--full"><label>Photos illustratives (4 maximum)</label><input class="register-file-input" name="services[__INDEX__][images][]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple><small class="register-hint">Images JPG, PNG ou WebP, 4 Mo maximum par image.</small></div>
                                </div>
                            </article>
                        </template>
                    </div>
                    <button type="button" class="register-secondary-button" data-add-service><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un autre service</button>
                    @error('services')<p class="register-field-error">{{ $message }}</p>@enderror
                    @error('services.*.skill_ids')<p class="register-field-error">{{ $message }}</p>@enderror
                </section>

                <section class="register-step" data-registration-step="4" aria-labelledby="documents-title">
                    <div class="register-step-heading"><span class="register-step-number">04</span><div><h2 id="documents-title">Documents et preuves</h2><p>Transmettez les justificatifs pertinents pour votre métier. Ils seront stockés dans un espace privé, sans URL publique.</p></div></div>
                    <p class="register-hint">Selon l’activité, vous pouvez fournir une pièce d’identité, un diplôme, une attestation, une licence, une référence ou une autre preuve utile. Les documents non nécessaires ne doivent pas être demandés.</p>
                    <div class="register-documents" data-documents-container>
                        <div class="register-document-row" data-document-row>
                            <div class="register-field"><label>Type de document</label><select class="register-select-input" name="documents[0][type]"><option value="identity">Pièce d’identité</option><option value="certificate">Certificat</option><option value="diploma">Diplôme</option><option value="license">Licence / autorisation</option><option value="reference">Référence / expérience</option><option value="other">Autre</option></select></div>
                            <div class="register-field"><label>Fichier</label><input class="register-file-input" name="documents[0][file]" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"><small class="register-hint">PDF ou image, 5 Mo maximum.</small></div>
                        </div>
                    </div>
                    <button type="button" class="register-secondary-button" data-add-document><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un document</button>
                    @error('documents.*.file')<p class="register-field-error">{{ $message }}</p>@enderror
                </section>

                <section class="register-step" data-registration-step="5" aria-labelledby="summary-title">
                    <div class="register-step-heading"><span class="register-step-number">05</span><div><h2 id="summary-title">Vérifier et envoyer le dossier</h2><p>Relisez les informations avant de créer votre compte professionnel.</p></div></div>
                    <div class="register-summary" data-registration-summary>
                        <p><strong>Identité :</strong> <span data-summary-name>—</span></p>
                        <p><strong>E-mail :</strong> <span data-summary-email>—</span></p>
                        <p><strong>Téléphone :</strong> <span data-summary-phone>—</span></p>
                        <p><strong>Ville :</strong> <span data-summary-city>—</span></p>
                        <p><strong>Catégories :</strong> <span data-summary-categories>—</span></p>
                        <p><strong>Compétences :</strong> <span data-summary-skills>—</span></p>
                        <p><strong>Services déclarés :</strong> <span data-summary-services>0</span></p>
                        <p><strong>Documents joints :</strong> <span data-summary-documents>0</span></p>
                    </div>
                    <p class="register-security-note"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Après l’envoi, votre compte pourra être utilisé pour compléter le dossier, mais votre profil restera privé et vos services resteront en brouillon jusqu’aux validations nécessaires.</p>
                </section>

                <div class="register-wizard-actions" data-wizard-actions>
                    <button type="button" class="register-secondary-button" data-registration-previous>Précédent</button>
                    <button type="button" class="register-submit" data-registration-next>Suivant <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    <button type="submit" class="register-submit" data-professional-submit><span>Ajouter mon espace professionnel</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </div>
        </form>

        <p class="register-login-link"><a href="{{ route('dashboard') }}">Retour à mon espace</a></p>
    </section>

    <aside class="register-aside" aria-labelledby="register-aside-title">
        <div class="register-aside__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
        <span class="register-aside__eyebrow">UN SEUL COMPTE, PLUSIEURS ESPACES</span>
        <h2 id="register-aside-title">Développez votre activité sans perdre votre historique client.</h2>
        <p>Votre espace professionnel sera distinct de votre espace client. L’approbation du dossier et la publication des services resteront des décisions séparées.</p>
        <ul class="register-aside__list">
            <li><i class="fa-solid fa-check" aria-hidden="true"></i> Vos données sont validées côté serveur.</li>
            <li><i class="fa-solid fa-check" aria-hidden="true"></i> Les documents professionnels restent privés.</li>
            <li><i class="fa-solid fa-check" aria-hidden="true"></i> Les services sont créés en brouillon.</li>
        </ul>
    </aside>
</div>
@endsection
