<div class="directory-filters" data-directory-filters>
    <div class="directory-filters__header">
        <div>
            <span class="eyebrow">FILTRES</span>
            <h2>Affiner les résultats</h2>
        </div>

        <button class="icon-button directory-filters__close" type="button" data-filters-close aria-label="Fermer les filtres">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <form method="GET" action="{{ $action }}" class="directory-filter-form" data-directory-form>
        <div class="form-field directory-filter-form__search">
            <label class="form-label" for="{{ $idPrefix }}-search">Recherche</label>
            <div class="form-control-wrapper">
                <i class="fa-solid fa-magnifying-glass form-control-icon" aria-hidden="true"></i>
                <input
                    id="{{ $idPrefix }}-search"
                    class="form-control form-control--with-icon"
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    placeholder="Métier, compétence ou service…"
                    autocomplete="off"
                >
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="{{ $idPrefix }}-profession">Profession</label>
            <input id="{{ $idPrefix }}-profession" class="form-control" type="text" name="profession" value="{{ $filters['profession'] ?? '' }}" placeholder="Ex. Développeur web">
        </div>

        <div class="form-field">
            <label class="form-label" for="{{ $idPrefix }}-category">Catégorie</label>
            <div class="form-select-wrapper">
                <select id="{{ $idPrefix }}-category" class="form-control form-select" name="category">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
        </div>

        <div class="form-field">
            <span class="form-label">Compétences</span>
            <div class="directory-skill-list">
                @foreach ($skills->take(12) as $skill)
                    <label class="directory-check">
                        <input type="checkbox" name="skills[]" value="{{ $skill->slug }}" @checked(in_array($skill->slug, $filters['skills'] ?? [], true))>
                        <span>{{ $skill->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="directory-filter-grid">
            <div class="form-field">
                <label class="form-label" for="{{ $idPrefix }}-city">Ville</label>
                <input id="{{ $idPrefix }}-city" class="form-control" type="text" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Ex. Goma">
            </div>

            <div class="form-field">
                <label class="form-label" for="{{ $idPrefix }}-province">Province</label>
                <input id="{{ $idPrefix }}-province" class="form-control" type="text" name="province" value="{{ $filters['province'] ?? '' }}" placeholder="Ex. Nord-Kivu">
            </div>
        </div>

        <div class="form-field">
            <span class="form-label">Budget</span>
            <div class="directory-filter-grid">
                <input class="form-control" type="number" name="min_price" min="0" step="0.01" value="{{ $filters['min_price'] ?? '' }}" placeholder="Minimum" aria-label="Prix minimum">
                <input class="form-control" type="number" name="max_price" min="0" step="0.01" value="{{ $filters['max_price'] ?? '' }}" placeholder="Maximum" aria-label="Prix maximum">
            </div>
            @error('max_price')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
            <div class="directory-filter-grid directory-filter-grid--currency">
                <select class="form-control form-select" name="currency" aria-label="Devise">
                    <option value="">Choisir une devise</option>
                    @foreach (['USD' => 'USD — Dollar', 'CDF' => 'CDF — Franc congolais', 'EUR' => 'EUR — Euro'] as $code => $label)
                        <option value="{{ $code }}" @selected(($filters['currency'] ?? '') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @error('currency')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="directory-filter-grid">
            <div class="form-field">
                <label class="form-label" for="{{ $idPrefix }}-rating">Note minimale</label>
                <div class="form-select-wrapper">
                    <select id="{{ $idPrefix }}-rating" class="form-control form-select" name="rating">
                        <option value="">Toutes</option>
                        @foreach ([5, 4, 3, 2, 1] as $rating)
                            <option value="{{ $rating }}" @selected((string) ($filters['rating'] ?? '') === (string) $rating)>{{ $rating }}+ étoiles</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
                </div>
            </div>

            <div class="form-field">
                <label class="form-label" for="{{ $idPrefix }}-availability">Disponibilité</label>
                <div class="form-select-wrapper">
                    <select id="{{ $idPrefix }}-availability" class="form-control form-select" name="availability">
                        <option value="">Toutes</option>
                        <option value="available" @selected(($filters['availability'] ?? '') === 'available')>Disponible</option>
                        <option value="unavailable" @selected(($filters['availability'] ?? '') === 'unavailable')>Indisponible</option>
                        <option value="unknown" @selected(($filters['availability'] ?? '') === 'unknown')>Non renseignée</option>
                    </select>
                    <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="{{ $idPrefix }}-verification">Vérification</label>
            <div class="form-select-wrapper">
                <select id="{{ $idPrefix }}-verification" class="form-control form-select" name="verification">
                    <option value="">Tous les profils publiables</option>
                    <option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>Vérifiés uniquement</option>
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="{{ $idPrefix }}-sort">Trier par</label>
            <div class="form-select-wrapper">
                <select id="{{ $idPrefix }}-sort" class="form-control form-select" name="sort">
                    <option value="relevance" @selected(($filters['sort'] ?? 'relevance') === 'relevance')>Pertinence</option>
                    <option value="rating" @selected(($filters['sort'] ?? '') === 'rating')>Meilleure note</option>
                    <option value="price_low" @selected(($filters['sort'] ?? '') === 'price_low')>Prix croissant</option>
                    <option value="price_high" @selected(($filters['sort'] ?? '') === 'price_high')>Prix décroissant</option>
                    <option value="newest" @selected(($filters['sort'] ?? '') === 'newest')>Nouveaux profils</option>
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
            @error('sort')
                <p class="form-error" role="alert">{{ $message }}</p>
            @enderror
        </div>

        <div class="directory-filter-actions">
            <a class="button button--ghost" href="{{ $action }}">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                Réinitialiser
            </a>
            <button class="button button--primary" type="submit">
                <i class="fa-solid fa-filter" aria-hidden="true"></i>
                Appliquer
            </button>
        </div>
    </form>
</div>
