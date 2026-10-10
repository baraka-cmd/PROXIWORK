<div class="directory-filters" data-directory-filters>
    <div class="directory-filters__header">
        <div>
            <span class="eyebrow">FILTRES</span>
            <h2>Affiner les services</h2>
        </div>
        <button class="icon-button directory-filters__close" type="button" data-filters-close aria-label="Fermer les filtres">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <form method="GET" action="{{ route('public.search') }}" class="directory-filter-form" data-directory-form>
        <div class="form-field directory-filter-form__search">
            <label class="form-label" for="service-filter-search">Recherche</label>
            <div class="form-control-wrapper">
                <i class="fa-solid fa-magnifying-glass form-control-icon" aria-hidden="true"></i>
                <input id="service-filter-search" class="form-control form-control--with-icon" type="search" name="search" value="{{ old('search', $filters['search'] ?? '') }}" placeholder="Service, métier, compétence…" autocomplete="off">
            </div>
        </div>

        <div class="form-field">
            <label class="form-label" for="service-filter-profession">Spécialité du professionnel</label>
            <input id="service-filter-profession" class="form-control" type="text" name="profession" value="{{ old('profession', $filters['profession'] ?? '') }}" placeholder="Ex. développeur web">
        </div>

        <div class="form-field">
            <label class="form-label" for="service-filter-category">Catégorie</label>
            <div class="form-select-wrapper">
                <select id="service-filter-category" class="form-control form-select" name="category">
                    <option value="">Toutes les catégories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected(old('category', $filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
        </div>

        <div class="form-field">
            <span class="form-label">Compétences</span>
            <div class="directory-skill-list">
                @foreach ($skills->take(16) as $skill)
                    <label class="directory-check">
                        <input type="checkbox" name="skills[]" value="{{ $skill->slug }}" @checked(collect(old('skills', $filters['skills'] ?? []))->containsStrict($skill->slug))>
                        <span>{{ $skill->name }}</span>
                    </label>
                @endforeach
            </div>
            @if (collect(old('skills', $filters['skills'] ?? []))->count() > 1)
                <label class="directory-check directory-check--mode">
                    <input type="checkbox" name="skills_mode" value="all" @checked(old('skills_mode', $filters['skills_mode'] ?? 'any') === 'all')>
                    <span>Exiger toutes les compétences choisies</span>
                </label>
            @endif
        </div>

        <div class="directory-filter-grid">
            <div class="form-field">
                <label class="form-label" for="service-filter-city">Ville</label>
                <input id="service-filter-city" class="form-control" type="text" name="city" value="{{ old('city', $filters['city'] ?? '') }}" placeholder="Ex. Goma">
            </div>
            <div class="form-field">
                <label class="form-label" for="service-filter-province">Province</label>
                <input id="service-filter-province" class="form-control" type="text" name="province" value="{{ old('province', $filters['province'] ?? '') }}" placeholder="Ex. Nord-Kivu">
            </div>
        </div>

        <div class="form-field">
            <span class="form-label">Budget</span>
            <div class="directory-filter-grid">
                <input class="form-control" type="number" name="min_price" min="0" step="0.01" value="{{ old('min_price', $filters['min_price'] ?? '') }}" placeholder="Minimum" aria-label="Prix minimum">
                <input class="form-control" type="number" name="max_price" min="0" step="0.01" value="{{ old('max_price', $filters['max_price'] ?? '') }}" placeholder="Maximum" aria-label="Prix maximum">
            </div>
            <label class="form-label" for="service-filter-currency">Devise (obligatoire pour comparer les prix)</label>
            <div class="form-select-wrapper">
                <select id="service-filter-currency" class="form-control form-select" name="currency">
                    <option value="">Choisir une devise</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency }}" @selected(old('currency', $filters['currency'] ?? '') === $currency)>{{ $currency }}</option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
            @error('currency')<small class="field-error" role="alert">{{ $message }}</small>@enderror
            @error('min_price')<small class="field-error" role="alert">{{ $message }}</small>@enderror
            @error('max_price')<small class="field-error" role="alert">{{ $message }}</small>@enderror
        </div>

        <div class="form-field">
            <label class="form-label" for="service-filter-billing-unit">Unité de facturation</label>
            <div class="form-select-wrapper">
                <select id="service-filter-billing-unit" class="form-control form-select" name="billing_unit">
                    <option value="">Toutes les unités</option>
                    @foreach ($billingUnits as $billingUnit)
                        <option value="{{ $billingUnit }}" @selected(old('billing_unit', $filters['billing_unit'] ?? '') === $billingUnit)>
                            {{ match ($billingUnit) {
                                'package' => 'Forfait / prestation',
                                'hour' => 'Par heure',
                                'day' => 'Par jour',
                                'project' => 'Par projet',
                                default => \Illuminate\Support\Str::headline(str_replace('_', ' ', $billingUnit)),
                            } }}
                        </option>
                    @endforeach
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
            @error('billing_unit')<small class="field-error" role="alert">{{ $message }}</small>@enderror
            <small class="form-help">Pour comparer un budget ou trier par prix, choisissez la même unité de facturation.</small>
        </div>

        <div class="directory-filter-grid">
            <div class="form-field">
                <label class="form-label" for="service-filter-rating">Note minimale</label>
                <div class="form-select-wrapper">
                    <select id="service-filter-rating" class="form-control form-select" name="rating">
                        <option value="">Toutes les notes</option>
                        @foreach ([5, 4, 3, 2, 1] as $rating)
                            <option value="{{ $rating }}" @selected((string) old('rating', $filters['rating'] ?? '') === (string) $rating)>{{ $rating }}+ étoiles</option>
                        @endforeach
                    </select>
                    <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="service-filter-availability">Disponibilité</label>
                <div class="form-select-wrapper">
                    <select id="service-filter-availability" class="form-control form-select" name="availability">
                        <option value="">Toutes</option>
                        <option value="available" @selected(old('availability', $filters['availability'] ?? '') === 'available')>Disponible</option>
                        <option value="unavailable" @selected(old('availability', $filters['availability'] ?? '') === 'unavailable')>Indisponible</option>
                        <option value="unknown" @selected(old('availability', $filters['availability'] ?? '') === 'unknown')>Non renseignée</option>
                    </select>
                    <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <label class="directory-check directory-check--verified">
            <input type="checkbox" name="verified_only" value="1" @checked(filter_var(old('verified_only', $filters['verified_only'] ?? false), FILTER_VALIDATE_BOOLEAN))>
            <span>Professionnels vérifiés uniquement</span>
        </label>

        <div class="form-field">
            <label class="form-label" for="service-filter-sort">Trier par</label>
            <div class="form-select-wrapper">
                <select id="service-filter-sort" class="form-control form-select" name="sort">
                    <option value="relevance" @selected(old('sort', $filters['sort'] ?? 'relevance') === 'relevance')>Pertinence / récents</option>
                    <option value="rating" @selected(old('sort', $filters['sort'] ?? '') === 'rating')>Mieux notés</option>
                    <option value="price_low" @selected(old('sort', $filters['sort'] ?? '') === 'price_low')>Prix croissant</option>
                    <option value="price_high" @selected(old('sort', $filters['sort'] ?? '') === 'price_high')>Prix décroissant</option>
                    <option value="newest" @selected(old('sort', $filters['sort'] ?? '') === 'newest')>Plus récents</option>
                </select>
                <i class="fa-solid fa-chevron-down form-select-icon" aria-hidden="true"></i>
            </div>
        </div>

        <div class="directory-filter-actions">
            <a class="button button--ghost" href="{{ route('public.search') }}">
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Réinitialiser
            </a>
            <button class="button button--primary" type="submit">
                <i class="fa-solid fa-filter" aria-hidden="true"></i> Appliquer
            </button>
        </div>
    </form>
</div>
