@extends('layouts.admin')

@section('page_eyebrow', 'PLATEFORME / DEMANDE')
@section('page_title', $request->title)
@section('page_description', 'Dossier transactionnel et historique des transitions.')

@section('page_actions')
    <a class="button button--secondary" href="{{ route('admin.service-requests.index') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        Demandes
    </a>
@endsection

@push('head')
    @unless (app()->environment('testing'))
        @vite(['resources/css/pages/admin/services-requests.css', 'resources/js/pages/admin/services-requests.js'])
    @endunless
@endpush

@section('content')
    @if (session('success'))
        <div class="admin-alert admin-alert--success" role="status">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="admin-alert admin-alert--danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="admin-detail-grid">
        <section class="admin-card">
            <div class="admin-card__header">
                <div>
                    <span class="admin-card__eyebrow">Dossier</span>
                    <h3>Participants</h3>
                </div>
                <span class="admin-status admin-status--{{ $request->status->value }}">
                    {{ $request->status->value }}
                </span>
            </div>

            <dl class="admin-detail-list">
                <div><dt>Client</dt><dd>{{ $request->client->name ?? '—' }}</dd></div>
                <div><dt>Professionnel</dt><dd>{{ $request->professional->user->name ?? '—' }}</dd></div>
                <div><dt>Service</dt><dd>{{ $request->service->title ?? '—' }}</dd></div>
                <div><dt>Adresse</dt><dd>{{ $request->address->label ?? '—' }}</dd></div>
                <div><dt>Budget</dt><dd>{{ $request->budget_min ?? '—' }} — {{ $request->budget_max ?? '—' }} {{ $request->currency }}</dd></div>
                <div><dt>Date souhaitée</dt><dd>{{ $request->desired_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>

            <p>{{ $request->description }}</p>

            @can('adminManage', $request)
                @if (in_array($request->status->value, ['draft', 'requested'], true))
                    <div class="admin-action-row">
                        <form method="POST" action="{{ route('admin.service-requests.cancel', $request) }}" data-confirm="Annuler administrativement cette demande ?">
                            @csrf
                            <label class="admin-field"><span>Motif obligatoire</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                            <button class="button button--secondary" type="submit">Annuler</button>
                        </form>

                        @if ($request->status->value === 'requested')
                            <form method="POST" action="{{ route('admin.service-requests.reject', $request) }}" data-confirm="Rejeter administrativement cette demande ?">
                                @csrf
                                <label class="admin-field"><span>Motif obligatoire</span><textarea name="reason" rows="3" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea></label>
                                <button class="button button--secondary" type="submit">Rejeter</button>
                            </form>
                        @endif
                    </div>
                @endif
            @endcan
        </section>

        <section class="admin-card">
            <div class="admin-card__header">
                <div>
                    <span class="admin-card__eyebrow">Traçabilité</span>
                    <h3>Historique des statuts</h3>
                </div>
            </div>

            <div class="admin-timeline">
                @forelse ($request->statusHistories as $history)
                    <article class="admin-timeline__item">
                        <div class="admin-timeline__dot"></div>
                        <div>
                            <strong>{{ $history->from_status }} → {{ $history->to_status }}</strong>
                            <p>{{ $history->actor->name ?? 'Système' }} · {{ $history->created_at?->format('d/m/Y H:i') }}</p>
                            @if ($history->reason)
                                <p>{{ $history->reason }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="admin-muted">Aucun historique.</p>
                @endforelse
            </div>
        </section>
    </div>

        @if ($request->quotation)
            <section class="admin-card admin-request-quotation">
                <div class="admin-card__header">
                    <div>
                        <span class="admin-card__eyebrow">Négociation commerciale</span>
                        <h3>Devis #{{ $request->quotation->getKey() }}</h3>
                    </div>
                    <span class="admin-status admin-status--{{ $request->quotation->status->value }}">{{ $request->quotation->status->value }}</span>
                </div>
                <dl class="admin-detail-list">
                    <div><dt>Offre courante</dt><dd>{{ $request->quotation->currentOffer ? '#'.$request->quotation->currentOffer->getKey().' · version '.$request->quotation->currentOffer->version : '—' }}</dd></div>
                    <div><dt>Offre acceptée</dt><dd>{{ $request->quotation->acceptedOffer ? '#'.$request->quotation->acceptedOffer->getKey().' · version '.$request->quotation->acceptedOffer->version : 'Aucune' }}</dd></div>
                    <div><dt>Commande liée</dt><dd>{{ $request->order ? $request->order->order_number : 'Aucune commande créée' }}</dd></div>
                </dl>

                <h4>Versions des propositions</h4>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Version</th><th>Émetteur</th><th>Montant</th><th>Durée</th><th>Validité</th><th>Création</th></tr></thead>
                        <tbody>
                        @forelse ($request->quotation->offers as $offer)
                            <tr>
                                <td><strong>V{{ $offer->version }}</strong> @if($request->quotation->accepted_offer_id === $offer->getKey())<span class="admin-status admin-status--completed">Acceptée</span>@elseif($request->quotation->current_offer_id === $offer->getKey())<span class="admin-status admin-status--in_progress">Courante</span>@endif</td>
                                <td>{{ $offer->creator?->name ?? 'Utilisateur indisponible' }} · {{ $offer->actor_type?->value ?? '—' }}</td>
                                <td>{{ number_format((float) $offer->amount, 2) }} {{ $offer->currency }}</td>
                                <td>{{ $offer->duration_value }} {{ $offer->duration_unit?->value ?? '' }}</td>
                                <td>{{ $offer->valid_until?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $offer->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Aucune proposition enregistrée.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <h4>Événements de négociation</h4>
                <div class="admin-timeline">
                    @forelse ($request->quotation->events as $event)
                        <article class="admin-timeline__item">
                            <div class="admin-timeline__dot"></div>
                            <div>
                                <strong>{{ str_replace('_', ' ', ucfirst($event->type)) }}</strong>
                                <p>{{ $event->actor?->name ?? 'Système' }} · {{ $event->created_at?->format('d/m/Y H:i') ?? '—' }}</p>
                                @if($event->offer_id)
                                    <p>Proposition associée : #{{ $event->offer_id }} · version {{ data_get($event->metadata, 'version', '—') }}</p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="admin-muted">Aucun événement de négociation enregistré.</p>
                    @endforelse
                </div>
                <p class="admin-muted">Cette consultation est en lecture seule. Les conditions d'une offre acceptée ne sont pas modifiables depuis l'administration.</p>
            </section>
        @endif
@endsection
