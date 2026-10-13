@extends('layouts.professional')

@section('title', 'Modifier mon profil professionnel — PROXIWORK')
@section('page_heading', 'Modifier mon profil professionnel')

@push('head')
    @unless (app()->environment('testing'))
        @vite('resources/css/pages/professional/profile.css')
    @endunless
@endpush

@section('content')
    <div class="dashboard-page-header">
        <div>
            <p class="eyebrow">Votre présence sur PROXIWORK</p>
            <h1>Modifier votre profil</h1>
            <p class="page-lead">Enregistrez chaque partie de votre profil avec les règles de confidentialité et de vérification du marketplace.</p>
        </div>
        <div class="dashboard-page-header__actions">
            <a class="button button--ghost" href="{{ route('professional.profile') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Retour au profil</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="profile-form-alert" role="alert" aria-live="assertive">
            <strong>Certains éléments doivent être corrigés.</strong>
            <p>Vérifiez les messages affichés dans les sections concernées, puis réessayez.</p>
        </div>
    @endif

    <section class="surface-card surface-card--lg profile-edit-section" aria-labelledby="profile-photo-heading">
        <div class="surface-card__header"><div><h2 id="profile-photo-heading">Photo de profil</h2><p class="field-help">La photo est stockée sur le disque privé. Elle n’est diffusée sur le profil public que si celui-ci est effectivement visible.</p></div></div>
        <div class="surface-card__body">
            <div class="profile-photo-editor">
                @if ($professional->user->profile?->avatar_path && \\Illuminate\\Support\\Facades\\Storage::disk('local')->exists($professional->user->profile->avatar_path))
                    <img class="avatar avatar--xl professional-profile-avatar" src="{{ route('professional.profile.photo') }}" alt="Photo de profil actuelle">
                @else
                    <span class="avatar avatar--xl professional-profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim(($professional->user->profile?->first_name ?? '').' '.($professional->user->profile?->last_name ?? '')) ?: $professional->user->name, 0, 1)) }}</span>
                @endif
                <div class="profile-photo-editor__controls">
                    <form method="POST" action="{{ route('professional.profile.photo.store') }}" enctype="multipart/form-data" class="profile-photo-upload">
                        @csrf
                        <label for="avatar">Ajouter ou remplacer la photo</label>
                        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required>
                        <p class="field-help">JPG, PNG ou WebP, 2 Mo maximum, image jusqu’à 2000 × 2000 pixels.</p>
                        @error('avatar')<p class="form-error">{{ $message }}</p>@enderror
                        <button class="button button--primary" type="submit"><i class="fa-solid fa-upload" aria-hidden="true"></i> Enregistrer la photo</button>
                    </form>
                    @if ($professional->user->profile?->avatar_path)
                        <form method="POST" action="{{ route('professional.profile.photo.destroy') }}" data-confirm="Supprimer votre photo de profil ?">
                            @csrf
                            @method('DELETE')
                            <button class="button button--ghost" type="submit"><i class="fa-regular fa-trash-can" aria-hidden="true"></i> Supprimer la photo</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="surface-card surface-card--lg profile-edit-section" aria-labelledby="profile-details-heading">
        <div class="surface-card__header"><div><h2 id="profile-details-heading">Identité et présentation professionnelle</h2><p class="field-help">Le prénom et le nom sont partagés avec l’identité du compte. L’e-mail, le téléphone et la sécurité du compte ne sont pas modifiés par ce formulaire.</p></div></div>
        <div class="surface-card__body">
            <form method="POST" action="{{ route('professional.profile.update') }}" class="form-stack">
                @csrf
                @method('PATCH')

                <fieldset class="form-fieldset">
                    <legend>Identité publique</legend>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="first_name">Prénom</label>
                            <input id="first_name" name="first_name" type="text" maxlength="100" autocomplete="given-name" value="{{ old('first_name', $professional->user->profile?->first_name) }}" aria-describedby="first-name-help">
                            <small id="first-name-help" class="field-help">Utilisé avec votre nom dans l’identité du compte.</small>
                            @error('first_name')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="last_name">Nom</label>
                            <input id="last_name" name="last_name" type="text" maxlength="100" autocomplete="family-name" value="{{ old('last_name', $professional->user->profile?->last_name) }}">
                            @error('last_name')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field form-field--full">
                            <label for="professional_title">Titre professionnel</label>
                            <input id="professional_title" name="professional_title" type="text" maxlength="160" autocomplete="organization-title" value="{{ old('professional_title', $professional->professional_title) }}" aria-describedby="professional-title-help">
                            <small id="professional-title-help" class="field-help">Exemple : « Électricien résidentiel et commercial ». Décrivez votre métier sans ajouter de qualification non confirmée.</small>
                            @error('professional_title')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field form-field--full">
                            <label for="description">Présentation professionnelle</label>
                            <textarea id="description" name="description" rows="6" maxlength="5000" aria-describedby="description-help">{{ old('description', $professional->description) }}</textarea>
                            <small id="description-help" class="field-help">Présentez votre spécialité, les travaux que vous réalisez et votre approche. 5 000 caractères maximum.</small>
                            @error('description')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="years_experience">Années d’expérience</label>
                            <input id="years_experience" name="years_experience" type="number" min="0" max="80" step="1" value="{{ old('years_experience', $professional->years_experience) }}">
                            @error('years_experience')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="starting_price">Prix indicatif de départ</label>
                            <input id="starting_price" name="starting_price" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('starting_price', $professional->starting_price) }}" aria-describedby="starting-price-help">
                            <small id="starting-price-help" class="field-help">Facultatif : le prix définitif est géré dans vos services et devis.</small>
                            @error('starting_price')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="currency">Devise du prix indicatif</label>
                            <select id="currency" name="currency">
                                <option value="">Choisir une devise</option>
                                @foreach (['USD' => 'USD — Dollar américain', 'CDF' => 'CDF — Franc congolais', 'EUR' => 'EUR — Euro'] as $code => $label)
                                    <option value="{{ $code }}" @selected(old('currency', $professional->currency) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('currency')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-fieldset">
                    <legend>Localisation et zone d’intervention</legend>
                    <p class="field-help">Indiquez votre zone professionnelle. Ces champs ne sont pas votre adresse personnelle. Les coordonnées GPS restent privées et ne sont pas affichées dans l’annuaire.</p>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="province">Province ou région</label>
                            <input id="province" name="province" type="text" maxlength="100" autocomplete="address-level1" value="{{ old('province', $professional->province) }}">
                            @error('province')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="city">Ville ou localité</label>
                            <input id="city" name="city" type="text" maxlength="100" autocomplete="address-level2" value="{{ old('city', $professional->city) }}">
                            @error('city')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="commune">Commune ou secteur</label>
                            <input id="commune" name="commune" type="text" maxlength="100" value="{{ old('commune', $professional->commune) }}">
                            @error('commune')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="service_radius_km">Rayon de service (km)</label>
                            <input id="service_radius_km" name="service_radius_km" type="number" min="1" max="500" step="1" value="{{ old('service_radius_km', $professional->service_radius_km) }}">
                            @error('service_radius_km')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="latitude">Latitude approximative (facultative)</label>
                            <input id="latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $professional->latitude) }}">
                            @error('latitude')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="longitude">Longitude approximative (facultative)</label>
                            <input id="longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $professional->longitude) }}">
                            @error('longitude')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-fieldset">
                    <legend>Disponibilité et visibilité</legend>
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="availability_status">Disponibilité pour de nouvelles demandes</label>
                            <select id="availability_status" name="availability_status" required>
                                @foreach ($availabilityOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('availability_status', $professional->availability_status?->value ?? 'unknown') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="field-help">Cette préférence n’annule ni ne modifie les commandes déjà acceptées.</small>
                            @error('availability_status')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="form-field">
                            <label for="visibility">Préférence de visibilité du profil</label>
                            <select id="visibility" name="visibility" required>
                                <option value="private" @selected(old('visibility', $professional->visibility) === 'private')>Privé — ne pas afficher dans l’annuaire</option>
                                <option value="public" @selected(old('visibility', $professional->visibility) === 'public')>Public — demander l’affichage dans l’annuaire</option>
                            </select>
                            <small class="field-help">L’affichage public exige aussi un compte et un profil actifs, une vérification administrative et au moins un service publié. Vous ne pouvez pas modifier vous-même ces états.</small>
                            @error('visibility')<p class="form-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a class="button button--ghost" href="{{ route('professional.profile') }}">Annuler</a>
                    <button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer les informations</button>
                </div>
            </form>
        </div>
    </section>

    <section id="professional-skills" class="surface-card surface-card--lg profile-edit-section" aria-labelledby="profile-skills-heading">
        <div class="surface-card__header"><div><h2 id="profile-skills-heading">Compétences et expérience associée</h2><p class="field-help">Sélectionnez uniquement les compétences que vous maîtrisez. Les compétences déclarées ne sont pas automatiquement des certifications vérifiées ni des services publiés.</p></div></div>
        <div class="surface-card__body">
            <form method="POST" action="{{ route('professional.profile.skills.update') }}" class="form-stack">
                @csrf
                @method('PATCH')
                <div class="profile-skills-grid">
                    @forelse ($skills as $skill)
                        @php
                            $currentSkill = $professional->skills->firstWhere('id', $skill->id);
                            $isSelected = in_array((int) $skill->id, array_map('intval', (array) old('skill_ids', $selectedSkillIds)), true);
                            $levelValue = old('proficiency_levels.'.$skill->id, $currentSkill?->pivot->proficiency_level);
                            $yearsValue = old('skill_years_experience.'.$skill->id, $currentSkill?->pivot->years_experience);
                            $isArchived = $skill->status?->value !== 'active';
                        @endphp
                        <article class="profile-skill-option">
                            <label class="profile-skill-option__selection" for="skill-{{ $skill->id }}">
                                <input id="skill-{{ $skill->id }}" type="checkbox" name="skill_ids[]" value="{{ $skill->id }}" @checked($isSelected)>
                                <span><strong>{{ $skill->name }}</strong>@if ($isArchived)<small>Compétence archivée, déjà associée à votre profil</small>@endif</span>
                            </label>
                            <div class="form-grid profile-skill-option__details">
                                <div class="form-field">
                                    <label for="proficiency-{{ $skill->id }}">Niveau déclaré</label>
                                    <select id="proficiency-{{ $skill->id }}" name="proficiency_levels[{{ $skill->id }}]">
                                        <option value="">Non renseigné</option>
                                        @foreach (['beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Avancé', 'expert' => 'Expert'] as $level => $levelLabel)
                                            <option value="{{ $level }}" @selected($levelValue === $level)>{{ $levelLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error('proficiency_levels.'.$skill->id)<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                                <div class="form-field">
                                    <label for="skill-years-{{ $skill->id }}">Années dans cette compétence</label>
                                    <input id="skill-years-{{ $skill->id }}" type="number" min="0" max="80" step="1" name="skill_years_experience[{{ $skill->id }}]" value="{{ $yearsValue }}">
                                    @error('skill_years_experience.'.$skill->id)<p class="form-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="state-empty state-empty--compact"><div><h3>Aucune compétence disponible</h3><p>Le catalogue de compétences ne contient actuellement aucun élément actif.</p></div></div>
                    @endforelse
                </div>
                @error('skill_ids')<p class="form-error">{{ $message }}</p>@enderror
                @error('skill_ids.*')<p class="form-error">{{ $message }}</p>@enderror
                @error('proficiency_levels.*')<p class="form-error">{{ $message }}</p>@enderror
                @error('skill_years_experience.*')<p class="form-error">{{ $message }}</p>@enderror
                <div class="form-actions">
                    <a class="button button--ghost" href="{{ route('professional.profile') }}">Annuler</a>
                    <button class="button button--primary" type="submit"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Enregistrer les compétences</button>
                </div>
            </form>
        </div>
    </section>

    <section class="surface-card surface-card--md profile-edit-section" aria-labelledby="profile-verification-heading">
        <div class="surface-card__header"><div><h2 id="profile-verification-heading">Vérification administrative</h2><p class="field-help">L’état de vérification est en lecture seule ici.</p></div></div>
        <div class="surface-card__body">
            <p>État actuel : <strong>{{ $professional->verification_status?->value ?? 'pending' }}</strong></p>
            <p class="field-help">Les justificatifs et les demandes de vérification se gèrent séparément. Le fait d’ajouter une compétence ou un document ne change pas automatiquement cet état.</p>
            <a class="button button--secondary" href="{{ route('professional.documents.index') }}">Consulter mes documents</a>
        </div>
    </section>
@endsection
