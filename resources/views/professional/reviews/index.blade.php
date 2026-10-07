@extends('layouts.professional')

@section('page_title', 'Avis clients')
@section('page_description', 'Consultez les retours reçus sur vos commandes et répondez aux avis publiés.')

@section('content')
    @if (session('success'))
        <div class="feedback feedback--success" role="status">{{ session('success') }}</div>
    @endif

    <section class="review-toolbar" aria-label="Filtres des avis">
        <form method="GET" class="review-filters">
            <label>
                <span>Note</span>
                <select name="rating">
                    <option value="">Toutes</option>
                    @for ($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" @selected($rating === $i)>{{ $i }}/5</option>
                    @endfor
                </select>
            </label>

            <label>
                <span>Statut</span>
                <select name="status">
                    <option value="">Tous</option>
                    <option value="published" @selected($status?->value === 'published')>Publié</option>
                    <option value="hidden" @selected($status?->value === 'hidden')>Masqué</option>
                </select>
            </label>

            <label>
                <span>Tri</span>
                <select name="sort">
                    <option value="latest" @selected($sort === 'latest')>Plus récents</option>
                    <option value="oldest" @selected($sort === 'oldest')>Plus anciens</option>
                    <option value="highest" @selected($sort === 'highest')>Meilleures notes</option>
                    <option value="lowest" @selected($sort === 'lowest')>Notes les plus basses</option>
                </select>
            </label>

            <button class="button button--primary" type="submit">
                <i class="fa-solid fa-filter" aria-hidden="true"></i> Filtrer
            </button>
        </form>
    </section>

    @if ($reviews->isEmpty())
        <section class="data-card data-card--empty">
            <i class="fa-regular fa-star" aria-hidden="true"></i>
            <h3>Aucun avis trouvé</h3>
            <p>Les avis laissés après des commandes terminées apparaîtront ici.</p>
        </section>
    @else
        <div class="review-list">
            @foreach ($reviews as $review)
                <article class="review-card">
                    <div class="review-card__top">
                        <div>
                            <strong>{{ $review->client?->name ?? 'Client' }}</strong>
                            <span class="review-card__date">{{ $review->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <div class="review-card__rating" aria-label="{{ $review->rating }} sur 5">
                            @for ($i = 1; $i <= 5; $i++)
                                <i class="{{ $i <= $review->rating ? 'fa-solid' : 'fa-regular' }} fa-star" aria-hidden="true"></i>
                            @endfor
                            <span>{{ $review->rating }}/5</span>
                        </div>
                    </div>

                    <div class="review-card__status">
                        <span class="status-badge status-badge--{{ $review->status->value }}">
                            {{ $review->status === \App\Enums\ReviewStatus::PUBLISHED ? 'Publié' : 'Masqué' }}
                        </span>
                    </div>

                    @if ($review->comment)
                        <p class="review-card__comment">{{ $review->comment }}</p>
                    @else
                        <p class="review-card__comment review-card__comment--muted">Le client n'a pas laissé de commentaire.</p>
                    @endif

                    @if ($review->response)
                        <div class="review-response">
                            <strong><i class="fa-solid fa-reply" aria-hidden="true"></i> Votre réponse</strong>
                            <p>{{ $review->response->response }}</p>
                        </div>
                    @elseif ($review->status === \App\Enums\ReviewStatus::PUBLISHED)
                        <form method="POST" action="{{ route('professional.reviews.respond', $review) }}" class="review-response-form">
                            @csrf
                            <label for="response-{{ $review->id }}">Répondre à cet avis</label>
                            <textarea id="response-{{ $review->id }}" name="response" maxlength="2000" rows="3" required placeholder="Merci pour votre retour..."></textarea>
                            @error('response')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                            <button class="button button--secondary" type="submit">Publier la réponse</button>
                        </form>
                    @endif
                </article>
            @endforeach
        </div>

        <nav class="pagination-wrap" aria-label="Pagination des avis">
            {{ $reviews->withQueryString()->links() }}
        </nav>
    @endif
@endsection
