@extends('layouts.auth')

@section('title', 'Dossier professionnel — PROXIWORK')
@section('meta_description', 'Présentez votre profil professionnel, vos compétences et vos services à PROXIWORK.')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/auth/register.css')
    @endunless
@endpush

@section('auth_content')
    @php($existingUser = $existingUser ?? null)
    <div class="register-page register-form-page professional-registration-page">
        <section class="register-card" aria-labelledby="register-title">
            <a class="register-back-link" href="{{ $existingUser ? route('client.profile') : route('register') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> {{ $existingUser ? 'Retour à mon profil client' : 'Choisir un autre parcours' }}</a>
            <div class="register-card__header">
                <span class="register-eyebrow"><i class="fa-solid fa-briefcase" aria-hidden="true"></i> DOSSIER PROFESSIONNEL</span>
                <h1 id="register-title">Présentez votre activité</h1>
                <p>{{ $existingUser ? 'Complétez les étapes professionnelles. Votre identité et votre accès client seront conservés.' : 'Complétez les cinq étapes. Votre profil et vos services resteront privés pendant leur examen.' }}</p>
            </div>

            @if ($errors->any())
                <div class="register-alert" role="alert" tabindex="-1"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><div><strong>Vérifiez les informations de votre dossier</strong><p>{{ $errors->first() }}</p></div></div>
            @endif

            <ol class="professional-registration-progress" aria-label="Étapes du dossier">
                <li data-progress-step="0" aria-current="step"><span>1</span><strong>Identité</strong></li>
                <li data-progress-step="1"><span>2</span><strong>Compétences</strong></li>
                <li data-progress-step="2"><span>3</span><strong>Services</strong></li>
                <li data-progress-step="3"><span>4</span><strong>Justificatifs</strong></li>
                <li data-progress-step="4"><span>5</span><strong>Récapitulatif</strong></li>
            </ol>

            <form method="POST" action="{{ $existingUser ? route('client.professional-profile.store') : route('register.professional.store') }}" class="register-form professional-registration-form" data-register-form data-professional-wizard data-account-first-name="{{ $existingUser?->profile?->first_name }}" data-account-last-name="{{ $existingUser?->profile?->last_name }}" data-validation-keys="{{ implode(',', $errors->keys()) }}" enctype="multipart/form-data" novalidate>
                @csrf
                <p class="register-field-error professional-wizard-validation" data-wizard-validation role="status" aria-live="polite" hidden></p>

                <section class="professional-registration-step" data-register-step="0" aria-labelledby="professional-step-identity">
                    <header><span class="register-step-kicker">ÉTAPE 1 SUR 5</span><h2 id="professional-step-identity">{{ $existingUser ? 'Votre identité et votre activité' : 'Identité et coordonnées' }}</h2><p>{{ $existingUser ? 'Votre compte existant reste votre identité principale. Nous utilisons ses coordonnées sans créer un second compte.' : 'Ces informations servent à créer votre accès et à vous présenter aux clients après approbation.' }}</p></header>
                    @if ($existingUser)
                        <div class="register-alert" role="status"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><strong>Compte client conservé</strong><p>{{ $existingUser->name }} · {{ $existingUser->email }}. Les deux espaces utiliseront la même connexion.</p></div></div>
                    @else
                    <div class="register-form-grid">
                        <div class="register-field"><label for="first_name">Prénom</label><div class="register-input"><i class="fa-regular fa-user" aria-hidden="true"></i><input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" minlength="2" maxlength="100" required autofocus></div>@error('first_name')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="last_name">Nom</label><div class="register-input"><i class="fa-regular fa-user" aria-hidden="true"></i><input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" minlength="2" maxlength="100" required></div>@error('last_name')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="email">Adresse e-mail</label><div class="register-input"><i class="fa-regular fa-envelope" aria-hidden="true"></i><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="255" required></div>@error('email')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="phone">Numéro de téléphone</label><div class="register-input"><i class="fa-solid fa-phone" aria-hidden="true"></i><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" inputmode="tel" maxlength="30" required></div>@error('phone')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                    </div>

                    <div class="register-field register-photo-field"><label for="profile_photo">Photo de profil <span>Facultative</span></label><input id="profile_photo" name="profile_photo" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small class="register-hint">JPG, PNG ou WebP, 4 Mo maximum.</small>@error('profile_photo')<p class="register-field-error">{{ $message }}</p>@enderror</div>

                    <div class="register-form-grid">
                        <div class="register-field"><label for="password">Mot de passe</label><div class="register-input"><i class="fa-solid fa-lock" aria-hidden="true"></i><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" required data-register-password><button class="register-password-toggle" type="button" data-register-password-toggle aria-label="Afficher le mot de passe" aria-pressed="false"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></div>
                            <div class="register-strength" id="password-strength" aria-live="polite"><div class="register-strength__top"><span>Sécurité du mot de passe</span><strong data-register-strength-label>À saisir</strong></div><div class="register-strength__bar" aria-hidden="true"><span data-register-strength-bar></span></div><ul class="register-checks" aria-label="Critères du mot de passe"><li data-register-check="length"><i class="fa-solid fa-circle" aria-hidden="true"></i> 12 caractères minimum. Une phrase de passe est acceptée.</li></ul></div>
                            @error('password')<p class="register-field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="register-field"><label for="password_confirmation">Confirmer le mot de passe</label><div class="register-input"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required data-register-confirm-password><button class="register-password-toggle" type="button" data-register-confirm-toggle aria-label="Afficher la confirmation du mot de passe" aria-pressed="false"><i class="fa-regular fa-eye" aria-hidden="true"></i></button></div><p class="register-match" data-register-match aria-live="polite"></p></div>
                    </div>
                    @endif
                    <div class="register-form-grid">
                        <div class="register-field"><label for="professional_title">Votre métier ou spécialité</label><div class="register-input"><i class="fa-solid fa-briefcase" aria-hidden="true"></i><input id="professional_title" name="professional_title" value="{{ old('professional_title') }}" placeholder="Ex. Électricien bâtiment" minlength="3" maxlength="160" required></div>@error('professional_title')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="business_name">Nom commercial <span>Facultatif</span></label><div class="register-input"><i class="fa-solid fa-store" aria-hidden="true"></i><input id="business_name" name="business_name" value="{{ old('business_name') }}" maxlength="160"></div>@error('business_name')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="province">Province ou région</label><div class="register-input"><i class="fa-solid fa-map" aria-hidden="true"></i><input id="province" name="province" value="{{ old('province') }}" autocomplete="address-level1" maxlength="100" required></div>@error('province')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="city">Ville</label><div class="register-input"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2" maxlength="100" required></div>@error('city')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="service_radius_km">Rayon d’intervention en kilomètres</label><div class="register-input"><i class="fa-solid fa-route" aria-hidden="true"></i><input id="service_radius_km" name="service_radius_km" type="number" value="{{ old('service_radius_km', 25) }}" min="0" max="500" required></div>@error('service_radius_km')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                        <div class="register-field"><label for="years_experience">Années d’expérience <span>Facultatif</span></label><div class="register-input"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><input id="years_experience" name="years_experience" type="number" value="{{ old('years_experience') }}" min="0" max="80"></div>@error('years_experience')<p class="register-field-error">{{ $message }}</p>@enderror</div>
                    </div>
                </section>

                <section class="professional-registration-step" data-register-step="1" aria-labelledby="professional-step-skills" hidden>
                    <header><span class="register-step-kicker">ÉTAPE 2 SUR 5</span><h2 id="professional-step-skills">Catégories et compétences</h2><p>Choisissez jusqu’à deux catégories, puis les compétences actives qui correspondent à votre expérience.</p></header>
                    <fieldset class="professional-category-picker"><legend>Vos catégories principales <span>(2 maximum)</span></legend>
                        @forelse ($categories as $category)
                            <label class="professional-category-option"><input type="checkbox" name="category_ids[]" value="{{ $category->id }}" data-category-choice @checked(in_array((string) $category->id, array_map('strval', old('category_ids', [])), true))><span><strong>{{ $category->name }}</strong>@if($category->description)<small>{{ $category->description }}</small>@endif</span></label>
                        @empty
                            <p class="register-hint">Aucune catégorie active n’est disponible pour le moment. Revenez plus tard ou contactez l’administration.</p>
                        @endforelse
                    </fieldset>
                    @error('category_ids')<p class="register-field-error">{{ $message }}</p>@enderror

                    <div class="professional-skill-picker" data-profile-skills>
                        <h3>Compétences correspondantes</h3>
                        <p class="register-hint">Sélectionnez au moins une compétence dans chaque catégorie choisie.</p>
                        @foreach ($categories as $category)
                            <fieldset class="professional-skill-group" data-skill-group="{{ $category->id }}" hidden>
                                <legend>{{ $category->name }}</legend>
                                @forelse ($category->skills as $skill)
                                    <label class="professional-skill-option" data-skill-choice data-category-id="{{ $category->id }}"><input type="checkbox" name="skill_ids[]" value="{{ $skill->id }}" data-profile-skill @checked(in_array((string) $skill->id, array_map('strval', old('skill_ids', [])), true))><span>{{ $skill->name }}</span></label>
                                @empty
                                    <p class="register-hint">Aucune compétence active n’est encore associée à cette catégorie.</p>
                                @endforelse
                            </fieldset>
                        @endforeach
                    </div>
                    @error('skill_ids')<p class="register-field-error">{{ $message }}</p>@enderror
                </section>

                <section class="professional-registration-step" data-register-step="2" aria-labelledby="professional-step-services" hidden>
                    <header><span class="register-step-kicker">ÉTAPE 3 SUR 5</span><h2 id="professional-step-services">Services proposés</h2><p>Décrivez une à huit prestations. Chaque service restera en brouillon pendant la vérification.</p></header>
                    <div class="professional-service-builder" data-service-builder>
                        <div class="professional-service-card" data-service-row data-service-index="0">
                            <div class="professional-service-card__heading"><h3>Prestation 1</h3><button class="register-remove-row" type="button" data-remove-service hidden>Supprimer</button></div>
                            <div class="register-form-grid">
                                <label class="register-field"><span>Catégorie</span><select name="services[0][category_id]" data-service-category required><option value="">Choisir une catégorie sélectionnée</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string) old('services.0.category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                                <label class="register-field"><span>Nom du service</span><input name="services[0][title]" value="{{ old('services.0.title') }}" minlength="3" maxlength="160" placeholder="Ex. Installation d’un lavabo" required></label>
                                <label class="register-field"><span>Tarification</span><select name="services[0][pricing_type]" data-pricing-type required><option value="quote">Sur devis</option><option value="fixed">Prix fixe</option><option value="from">À partir de</option><option value="range">Fourchette de prix</option></select></label>
                                <label class="register-field" data-price-field><span>Prix</span><input name="services[0][price]" type="number" min="0" step="0.01" value="{{ old('services.0.price') }}" placeholder="0,00"></label>
                                <label class="register-field" data-price-min-field hidden><span>Prix minimum</span><input name="services[0][price_min]" type="number" min="0" step="0.01" value="{{ old('services.0.price_min') }}"></label>
                                <label class="register-field" data-price-max-field hidden><span>Prix maximum</span><input name="services[0][price_max]" type="number" min="0" step="0.01" value="{{ old('services.0.price_max') }}"></label>
                                <label class="register-field"><span>Devise</span><select name="services[0][currency]" required><option value="USD">USD</option><option value="CDF">CDF</option></select></label>
                                <label class="register-field"><span>Mode d’exécution</span><select name="services[0][execution_mode]" required><option value="client_location">Chez le client ou sur place</option><option value="remote">À distance</option></select></label>
                                <label class="register-field"><span>Durée indicative en minutes <small>Facultatif</small></span><input name="services[0][estimated_duration_minutes]" type="number" min="1" max="100000" value="{{ old('services.0.estimated_duration_minutes') }}"></label>
                            </div>
                            <div class="professional-service-skills" data-service-skills><span>Compétences utilisées pour ce service</span><div data-service-skill-options>@foreach($categories as $category)@foreach($category->skills as $skill)<label data-service-skill data-category-id="{{ $category->id }}" data-skill-id="{{ $skill->id }}" hidden><input type="checkbox" name="services[0][skill_ids][]" value="{{ $skill->id }}"><span>{{ $skill->name }}</span></label>@endforeach @endforeach</div></div>
                            <label class="register-field"><span>Description détaillée</span><textarea name="services[0][description]" rows="4" minlength="20" maxlength="5000" placeholder="Expliquez ce que comprend cette prestation, ses limites et les informations utiles au client." required>{{ old('services.0.description') }}</textarea></label>
                        </div>
                    </div>
                    <button class="button button--secondary professional-add-service" type="button" data-add-service><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un autre service</button>
                    @error('services')<p class="register-field-error">{{ $message }}</p>@enderror
                </section>

                <section class="professional-registration-step" data-register-step="3" aria-labelledby="professional-step-documents" hidden>
                    <header><span class="register-step-kicker">ÉTAPE 4 SUR 5</span><h2 id="professional-step-documents">Justificatifs professionnels</h2><p>Transmettez les pièces utiles à votre métier. Les documents sont facultatifs lorsque votre activité ne les exige pas.</p></header>
                    <div class="professional-document-list" data-document-list>
                        <div class="professional-document-row" data-document-row data-document-index="0">
                            <label class="register-field"><span>Type de justificatif</span><select name="documents[0][type]"><option value="certificate">Certificat ou diplôme</option><option value="training">Attestation de formation</option><option value="license">Licence ou autorisation</option><option value="reference">Référence professionnelle</option><option value="other">Autre justificatif</option></select></label>
                            <label class="register-field"><span>Fichier <small>Facultatif</small></span><input type="file" name="documents[0][file]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"><small class="register-hint">PDF ou image, 10 Mo maximum. Stockage privé et accès réservé aux personnes habilitées.</small></label>
                            <button class="register-remove-row" type="button" data-remove-document hidden>Supprimer ce fichier</button>
                        </div>
                    </div>
                    <button class="button button--secondary" type="button" data-add-document><i class="fa-solid fa-plus" aria-hidden="true"></i> Ajouter un justificatif</button>
                </section>

                <section class="professional-registration-step" data-register-step="4" aria-labelledby="professional-step-review" hidden>
                    <header><span class="register-step-kicker">ÉTAPE 5 SUR 5</span><h2 id="professional-step-review">Vérifiez votre dossier</h2><p>Relisez les informations avant de transmettre votre demande.</p></header>
                    <dl class="professional-registration-summary">
                        <div><dt>Identité</dt><dd data-summary-name>À compléter</dd></div>
                        <div><dt>Activité et zone</dt><dd data-summary-activity>À compléter</dd></div>
                        <div><dt>Catégories</dt><dd data-summary-categories>À sélectionner</dd></div>
                        <div><dt>Compétences</dt><dd data-summary-skills>À sélectionner</dd></div>
                        <div><dt>Services</dt><dd data-summary-services>1 service</dd></div>
                        <div><dt>Justificatifs</dt><dd data-summary-documents>Aucun fichier ajouté</dd></div>
                    </dl>
                    <div class="professional-review-notice"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><p>Après l’envoi, votre profil restera privé. L’équipe PROXIWORK examinera votre dossier et vos documents. Vos services seront enregistrés comme brouillons et devront être publiés séparément après validation.</p></div>
                    <label class="register-consent"><input type="checkbox" name="terms" value="1" required><span>J’accepte les conditions d’utilisation et la politique de confidentialité. Je confirme que les informations fournies sont exactes.</span></label>
                    @error('terms')<p class="register-field-error">{{ $message }}</p>@enderror
                </section>

                <div class="professional-registration-controls">
                    <button class="button button--secondary" type="button" data-step-previous hidden><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Précédent</button>
                    <span class="professional-registration-controls__spacer"></span>
                    <button class="button button--primary" type="button" data-step-next>Suivant <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    <button class="register-submit" type="submit" data-register-submit data-submit-registration hidden><span>Transmettre mon dossier</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            </form>

            <template id="professional-service-template">
                <div class="professional-service-card" data-service-row data-service-index="__INDEX__">
                    <div class="professional-service-card__heading"><h3>Prestation __NUMBER__</h3><button class="register-remove-row" type="button" data-remove-service>Supprimer</button></div>
                    <div class="register-form-grid">
                        <label class="register-field"><span>Catégorie</span><select name="services[__INDEX__][category_id]" data-service-category required><option value="">Choisir une catégorie sélectionnée</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                        <label class="register-field"><span>Nom du service</span><input name="services[__INDEX__][title]" minlength="3" maxlength="160" placeholder="Ex. Installation d’un lavabo" required></label>
                        <label class="register-field"><span>Tarification</span><select name="services[__INDEX__][pricing_type]" data-pricing-type required><option value="quote">Sur devis</option><option value="fixed">Prix fixe</option><option value="from">À partir de</option><option value="range">Fourchette de prix</option></select></label>
                        <label class="register-field" data-price-field><span>Prix</span><input name="services[__INDEX__][price]" type="number" min="0" step="0.01"></label>
                        <label class="register-field" data-price-min-field hidden><span>Prix minimum</span><input name="services[__INDEX__][price_min]" type="number" min="0" step="0.01"></label>
                        <label class="register-field" data-price-max-field hidden><span>Prix maximum</span><input name="services[__INDEX__][price_max]" type="number" min="0" step="0.01"></label>
                        <label class="register-field"><span>Devise</span><select name="services[__INDEX__][currency]" required><option value="USD">USD</option><option value="CDF">CDF</option></select></label>
                        <label class="register-field"><span>Mode d’exécution</span><select name="services[__INDEX__][execution_mode]" required><option value="client_location">Chez le client ou sur place</option><option value="remote">À distance</option></select></label>
                        <label class="register-field"><span>Durée indicative en minutes <small>Facultatif</small></span><input name="services[__INDEX__][estimated_duration_minutes]" type="number" min="1" max="100000"></label>
                    </div>
                    <div class="professional-service-skills" data-service-skills><span>Compétences utilisées pour ce service</span><div data-service-skill-options>@foreach($categories as $category)@foreach($category->skills as $skill)<label data-service-skill data-category-id="{{ $category->id }}" data-skill-id="{{ $skill->id }}" hidden><input type="checkbox" name="services[__INDEX__][skill_ids][]" value="{{ $skill->id }}"><span>{{ $skill->name }}</span></label>@endforeach @endforeach</div></div>
                    <label class="register-field"><span>Description détaillée</span><textarea name="services[__INDEX__][description]" rows="4" minlength="20" maxlength="5000" placeholder="Expliquez ce que comprend cette prestation, ses limites et les informations utiles au client." required></textarea></label>
                </div>
            </template>

            <template id="professional-document-template">
                <div class="professional-document-row" data-document-row data-document-index="__INDEX__">
                    <label class="register-field"><span>Type de justificatif</span><select name="documents[__INDEX__][type]"><option value="certificate">Certificat ou diplôme</option><option value="training">Attestation de formation</option><option value="license">Licence ou autorisation</option><option value="reference">Référence professionnelle</option><option value="other">Autre justificatif</option></select></label>
                    <label class="register-field"><span>Fichier <small>Facultatif</small></span><input type="file" name="documents[__INDEX__][file]" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"><small class="register-hint">PDF ou image, 10 Mo maximum. Le fichier reste privé.</small></label>
                    <button class="register-remove-row" type="button" data-remove-document>Supprimer ce fichier</button>
                </div>
            </template>
        </section>
    </div>
@endsection

@push('scripts')
    @unless (app()->environment('testing'))
        @vite('resources/js/pages/auth/register.js')
    @endunless
@endpush
