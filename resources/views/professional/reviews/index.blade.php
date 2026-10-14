@extends('layouts.professional')

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/professional/reviews-messages.css', 'resources/js/pages/professional/reviews-messages.js'])
    @endunless
@endpush

@section('page_title', 'Réputation et avis')
@section('page_description', 'Comprenez les retours de vos clients, répondez avec professionnalisme et suivez votre réputation.')

@section('content')
    @if (session('success'))
        <div class="feedback feedback--success" role="status" aria-live="polite">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="feedback feedback--error" role="alert">
            <strong>Vérifiez les informations saisies.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="reputation-overview" aria-labelledby="reputation-title">
        <div class="reputation-overview__heading">
            <div>
                <span class="eyebrow">VOTRE RÉPUTATION</span>
                <h2 id="reputation-title">La satisfaction de vos clients</h2>
                <p>Seuls les avis publiés sont inclus dans la note affichée.</p>
            </div>
            <form method="GET" class="reputation-period">
                @foreach (request()->except('period', 'page') as $key => $value)
                    @if (is_scalar($value))
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="reputation-period">Période d’analyse</label>
                <select id="reputation-period" name="period" onchange="this.form.submit()">
                    <option value="7" @selected(($filters['period'] ?? '30') === '7')>7 derniers jours</option>
                    <option value="30" @selected(($filters['period'] ?? '30') === '30')>30 derniers jours</option>
                    <option value="90" @selected(($filters['period'] ?? '30') === '90')>90 derniers jours</option>
                </select>
            </form>
        </div>

        <div class="reputation-stat-grid">
            <article class="reputation-stat reputation-stat--primary">
                <span class="reputation-stat__label">Note moyenne</span>
                @if ($summary['total'] > 0)
                    <strong class="reputation-stat__value">{{ number_format($summary['average'], 2, ',', ' ') }}<small>/5</small></strong>
                    <span class="reputation-stat__hint" aria-label="Nombre d'avis">{{ $summary['total'] }} avis publiés</span>
                @else
                    <strong class="reputation-stat__value reputation-stat__value--empty">—</strong>
                    <span class="reputation-stat__hint">Aucun avis pour le moment</span>
                @endif
            </article>
            <article class="reputation-stat">
                <span class="reputation-stat__label">Avis récents</span>
                <strong class="reputation-stat__value">{{ $summary['recent_count'] }}</strong>
                <span class="reputation-stat__hint">Sur {{ $summary['period_days'] }} jours</span>
            </article>
            <article class="reputation-stat">
                <span class="reputation-stat__label">Sans réponse publiée</span>
                <strong class="reputation-stat__value">{{ $summary['unanswered'] }}</strong>
                <span class="reputation-stat__hint">Avis qui peuvent nécessiter votre attention</span>
            </article>
            <article class="reputation-stat">
                <span class="reputation-stat__label">Avec réponse publiée</span>
                <strong class="reputation-stat__value">{{ $summary['answered'] }}</strong>
                <span class="reputation-stat__hint">Réponses visibles aux clients</span>
            </article>
        </div>

        <div class="reputation-analysis">
            <section class="reputation-distribution" aria-labelledby="distribution-title">
                <h3 id="distribution-title">Répartition des notes</h3>
                @foreach ([5, 4, 3, 2, 1] as $stars)
                    @php
                        $count = $summary['distribution'][$stars];
                        $percent = $summary['total'] > 0 ? (int) round(($count / $summary['total']) * 100) : 0;
                    @endphp
                    <div class="rating-distribution-row">
                        <span>{{ $stars }} <i class="fa-solid fa-star" aria-hidden="true"></i></span>
                        <div class="rating-distribution-track" role="progressbar" aria-label="{{ $stars }} étoiles" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                            <span style="width: {{ $percent }}%"></span>
                        </div>
                        <span class="rating-distribution-count">{{ $count }}</span>
                    </div>
                @endforeach
            </section>
            <section class="reputation-trend" aria-labelledby="trend-title">
                <h3 id="trend-title">Évolution de la note</h3>
                @if ($summary['recent_average'] !== null && $summary['previous_average'] !== null)
                    <strong class="reputation-trend__value">
                        {{ number_format($summary['recent_average'], 2, ',', ' ') }}/5
                    </strong>
                    <p>
                        @if ($summary['average_change'] > 0)
                            <span class="trend-positive"><i class="fa-solid fa-arrow-trend-up" aria-hidden="true"></i> +{{ number_format($summary['average_change'], 2, ',', ' ') }}</span>
                        @elseif ($summary['average_change'] < 0)
                            <span class="trend-negative"><i class="fa-solid fa-arrow-trend-down" aria-hidden="true"></i> {{ number_format($summary['average_change'], 2, ',', ' ') }}</span>
                        @else
                            <span>Stable sur les deux périodes</span>
                        @endif
                        par rapport aux {{ $summary['period_days'] }} jours précédents
                    </p>
                    <small>{{ $summary['recent_count'] }} avis sur la période récente, contre {{ $summary['previous_count'] }} sur la précédente.</small>
                @else
                    <strong class="reputation-trend__value">Données insuffisantes</strong>
                    <p>Une comparaison sera affichée lorsque les deux périodes auront des avis publiés.</p>
                @endif
            </section>
        </div>

        <section class="reputation-themes" aria-labelledby="themes-title">
            <div>
                <h3 id="themes-title">Thèmes récurrents des commentaires</h3>
                <p>Analyse indicative par mots-clés réellement présents dans les commentaires, pas une décision automatique de modération.</p>
            </div>
            @if ($summary['total'] < 5 || $summary['themes']['sample_count'] < 5)
                <p class="reputation-themes__empty">Il faut au moins cinq avis publiés et commentés pour afficher des tendances. Avec peu de retours, lisez directement les commentaires ci-dessous.</p>
            @elseif (count($summary['themes']['items']) === 0)
                <p class="reputation-themes__empty">Aucun thème récurrent n’a été détecté dans les commentaires analysés. Cela ne signifie pas qu’aucun problème n’existe.</p>
            @else
                <div class="reputation-themes__list">
                    @foreach ($summary['themes']['items'] as $theme)
                        <div class="reputation-theme">
                            <div class="reputation-theme__heading">
                                <strong>{{ $theme['label'] }}</strong>
                                <span>{{ $theme['mentions'] }} mention(s) · {{ $theme['percentage'] }} % des commentaires analysés</span>
                            </div>
                            <div class="rating-distribution-track" role="progressbar" aria-label="{{ $theme['label'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $theme['percentage'] }}">
                                <span style="width: {{ $theme['percentage'] }}%"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </section>

    <section class="review-toolbar" aria-label="Recherche et filtres des avis">
        <div class="review-toolbar__heading">
            <div>
                <h2>Vos avis clients</h2>
                <p>Les filtres sont appliqués côté serveur et peuvent être combinés.</p>
            </div>
            <span class="review-total">{{ $reviews->total() }} résultat(s)</span>
        </div>
        <form method="GET" class="review-filters">
            <input type="hidden" name="period" value="{{ $filters['period'] ?? '30' }}">
            <label>
                <span>Recherche</span>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="120" placeholder="Mot-clé ou nom du service">
            </label>
            <label>
                <span>Note</span>
                <select name="rating">
                    <option value="">Toutes les notes</option>
                    @for ($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" @selected($rating === $i)>{{ $i }}/5 étoiles</option>
                    @endfor
                </select>
            </label>
            <label>
                <span>État de l’avis</span>
                <select name="status">
                    <option value="">Tous les états</option>
                    <option value="published" @selected($status?->value === 'published')>Publié</option>
                    <option value="hidden" @selected($status?->value === 'hidden')>Masqué par la modération</option>
                </select>
            </label>
            <label>
                <span>Service</span>
                <select name="service_id">
                    <option value="">Tous mes services</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected((string) ($filters['service_id'] ?? '') === (string) $service->id)>{{ $service->title }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Réponse</span>
                <select name="response">
                    <option value="">Avec ou sans réponse</option>
                    <option value="unanswered" @selected(($filters['response'] ?? '') === 'unanswered')>Sans réponse publiée</option>
                    <option value="answered" @selected(($filters['response'] ?? '') === 'answered')>Avec réponse publiée</option>
                </select>
            </label>
            <label>
                <span>Commentaire</span>
                <select name="comment">
                    <option value="">Avec ou sans commentaire</option>
                    <option value="with" @selected(($filters['comment'] ?? '') === 'with')>Avec commentaire</option>
                    <option value="without" @selected(($filters['comment'] ?? '') === 'without')>Sans commentaire</option>
                </select>
            </label>
            <label>
                <span>Publié à partir du</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label>
                <span>Publié jusqu’au</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </label>
            <label>
                <span>Ordre</span>
                <select name="sort">
                    <option value="latest" @selected($sort === 'latest')>Plus récents</option>
                    <option value="oldest" @selected($sort === 'oldest')>Plus anciens</option>
                    <option value="highest" @selected($sort === 'highest')>Meilleures notes</option>
                    <option value="lowest" @selected($sort === 'lowest')>Notes les plus basses</option>
                </select>
            </label>
            <div class="review-filter-actions">
                <button class="button button--primary" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Appliquer</button>
                <a class="button button--ghost" href="{{ route('professional.reviews') }}">Réinitialiser</a>
            </div>
        </form>
    </section>

    @if ($reviews->isEmpty())
        <section class="data-card data-card--empty review-empty-state">
            <i class="fa-regular fa-star" aria-hidden="true"></i>
            <h3>Aucun avis ne correspond à ces filtres</h3>
            <p>Essayez d’élargir la période ou de réinitialiser les filtres. Les avis apparaissent après la fin d’une commande admissible.</p>
            <a class="button button--secondary" href="{{ route('professional.reviews') }}">Voir tous les avis</a>
        </section>
    @else
        <div class="review-list">
            @foreach ($reviews as $review)
                <article class="review-card" aria-labelledby="review-title-{{ $review->id }}">
                    <div class="review-card__top">
                        <div class="review-card__identity">
                            <span class="review-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
                            <div>
                                <strong id="review-title-{{ $review->id }}">Client PROXIWORK</strong>
                                <span class="review-card__date">Publié le {{ $review->published_at?->format('d/m/Y à H:i') ?? $review->created_at?->format('d/m/Y à H:i') }}</span>
                            </div>
                        </div>
                        <div class="review-card__rating" aria-label="{{ $review->rating }} étoiles sur 5">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="{{ $i <= $review->rating ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>
                            @endfor
                            <span>{{ $review->rating }}/5</span>
                        </div>
                    </div>

                    <div class="review-card__metadata">
                        <span class="status-badge status-badge--{{ $review->status->value }}">
                            <i class="fa-solid {{ $review->status === \App\Enums\ReviewStatus::PUBLISHED ? 'fa-circle-check' : 'fa-eye-slash' }}" aria-hidden="true"></i>
                            {{ $review->status === \App\Enums\ReviewStatus::PUBLISHED ? 'Publié' : 'Masqué par la modération' }}
                        </span>
                        @if ($review->order)
                            <span class="review-reference"><i class="fa-solid fa-receipt" aria-hidden="true"></i> Commande #{{ $review->order->id }}</span>
                        @endif
                        @if ($review->order?->serviceRequest?->service)
                            <span class="review-service"><i class="fa-solid fa-briefcase" aria-hidden="true"></i>
                                <a href="{{ route('public.services.show', ['service' => $review->order->serviceRequest->service->slug]) }}">{{ $review->order->serviceRequest->service->title }}</a>
                            </span>
                        @endif
                    </div>

                    @if (filled($review->comment))
                        <p class="review-card__comment">{{ $review->comment }}</p>
                    @else
                        <p class="review-card__comment review-card__comment--muted"><i class="fa-regular fa-comment-dots" aria-hidden="true"></i> Le client a laissé une note sans commentaire.</p>
                    @endif

                    @if ($review->response)
                        <div class="review-response">
                            <strong><i class="fa-solid fa-reply" aria-hidden="true"></i> Votre réponse</strong>
                            @if ($review->response->status === \App\Enums\ReviewStatus::PUBLISHED)
                                <p>{{ $review->response->response }}</p>
                                <small>Réponse publiée le {{ $review->response->created_at?->format('d/m/Y à H:i') }}</small>
                            @else
                                <p>Votre réponse n’est pas actuellement visible publiquement, car elle fait l’objet d’une modération.</p>
                            @endif
                        </div>
                    @elseif ($review->status === \App\Enums\ReviewStatus::PUBLISHED)
                        <form method="POST" action="{{ route('professional.reviews.respond', $review) }}" class="review-response-form" data-review-response-form>
                            @csrf
                            <label for="response-{{ $review->id }}">Répondre à cet avis</label>
                            <textarea id="response-{{ $review->id }}" name="response" maxlength="2000" minlength="1" rows="3" required placeholder="Remerciez le client ou expliquez avec calme comment vous prenez son retour en compte."></textarea>
                            <small>Votre réponse sera publique. Ne partagez aucune coordonnée ni information privée sur le client ou sa commande.</small>
                            <button class="button button--secondary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Publier la réponse</button>
                        </form>
                    @endif

                    @if ($review->status === \App\Enums\ReviewStatus::PUBLISHED)
                        <details class="review-report">
                            <summary><i class="fa-regular fa-flag" aria-hidden="true"></i> Signaler un abus à PROXIWORK</summary>
                            <form method="POST" action="{{ route('professional.reviews.report', $review) }}" class="review-report__form">
                                @csrf
                                <input type="hidden" name="target_type" value="review">
                                <input type="hidden" name="target_id" value="{{ $review->id }}">
                                <label for="report-reason-{{ $review->id }}">Motif du signalement</label>
                                <select id="report-reason-{{ $review->id }}" name="reason_code" required>
                                    <option value="">Sélectionner un motif</option>
                                    <option value="SPAM">Spam ou contenu répétitif</option>
                                    <option value="FRAUD">Fraude ou avis suspect</option>
                                    <option value="HARASSMENT">Harcèlement ou menace</option>
                                    <option value="ABUSIVE_CONTENT">Contenu injurieux</option>
                                    <option value="INAPPROPRIATE_CONTENT">Contenu inapproprié ou données privées</option>
                                    <option value="OTHER">Autre motif</option>
                                </select>
                                <label for="report-description-{{ $review->id }}">Détails utiles (facultatif)</label>
                                <textarea id="report-description-{{ $review->id }}" name="description" maxlength="5000" rows="2" placeholder="Expliquez factuellement pourquoi cet avis doit être examiné."></textarea>
                                <small>Un signalement ne masque pas automatiquement l’avis. La décision appartient à l’équipe de modération.</small>
                                <button class="button button--ghost" type="submit">Transmettre le signalement</button>
                            </form>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>

        <nav class="pagination-wrap" aria-label="Pagination des avis">
            {{ $reviews->withQueryString()->links() }}
        </nav>
    @endif
@endsection
