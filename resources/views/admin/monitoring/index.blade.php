@extends('layouts.admin')

@section('title', 'Supervision technique')
@section('page_title', 'Supervision technique')
@section('page_description', 'État opérationnel des traitements, notifications, paiements, fichiers et services.')

@section('content')
    @php
        $formatBytes = static function (?int $bytes): string {
            if ($bytes === null) {
                return 'Non disponible';
            }

            if ($bytes < 1024) {
                return $bytes.' o';
            }

            $units = ['Ko', 'Mo', 'Go', 'To'];
            $value = $bytes / 1024;
            foreach ($units as $unit) {
                if ($value < 1024 || $unit === 'To') {
                    return number_format($value, 1, ',', ' ').' '.$unit;
                }
                $value /= 1024;
            }

            return number_format($value, 1, ',', ' ').' To';
        };
        $statusLabel = static fn (string $status): string => $status === 'available'
            ? 'Disponible'
            : ($status === 'unavailable' ? 'Indisponible' : 'Non mesuré');
        $workerStatusLabel = match ($monitoring['queues']['worker_status'] ?? null) {
            'active' => 'Heartbeat récent',
            'inactive' => 'Aucun heartbeat récent',
            default => 'Non instrumenté',
        };
    @endphp

    <div class="admin-page-stack">
        <section class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2>État général</h2>
                    <p>Dernière vérification : {{ $monitoring['checked_at']->format('d/m/Y à H:i:s') }}</p>
                </div>
                <span class="status-badge {{ $monitoring['application']['database'] === 'available' ? 'status-badge--success' : 'status-badge--danger' }}">
                    Base de données : {{ $statusLabel($monitoring['application']['database']) }}
                </span>
            </div>
            <div class="admin-stats-grid">
                <article class="admin-stat-card">
                    <span>Jobs en attente</span>
                    <strong>{{ number_format($monitoring['queues']['pending']) }}</strong>
                    <small>Réservés : {{ number_format($monitoring['queues']['reserved']) }}</small>
                </article>
                <article class="admin-stat-card">
                    <span>Jobs échoués</span>
                    <strong>{{ number_format($monitoring['failed_jobs']['total']) }}</strong>
                    <small>Enregistrements failed_jobs</small>
                </article>
                <article class="admin-stat-card">
                    <span>Livraisons en anomalie</span>
                    <strong>{{ number_format($monitoring['notifications']['attention']) }}</strong>
                    <small>Échecs ou résultat inconnu</small>
                </article>
                <article class="admin-stat-card">
                    <span>Paiements à examiner</span>
                    <strong>{{ number_format($monitoring['payments']['attention']) }}</strong>
                    <small>Transactions inconnues ou paiements en attente depuis plus de 15 minutes</small>
                </article>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card__header">
                <div>
                    <h2>77–78. Files et tâches échouées</h2>
                    <p>Les compteurs reflètent le backend de queue configuré. Les payloads et traces d'exception restent masqués.</p>
                </div>
            </div>
            @if (! $monitoring['queues']['available'])
                <p class="admin-empty-state">La table de queue n'est pas disponible.</p>
            @else
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead><tr><th>File</th><th>Total</th><th>En attente</th><th>Réservées</th></tr></thead>
                        <tbody>
                            @forelse ($monitoring['queues']['queues'] as $queue)
                                <tr>
                                    <td>{{ $queue['name'] }}</td>
                                    <td>{{ number_format($queue['total']) }}</td>
                                    <td>{{ number_format($queue['pending']) }}</td>
                                    <td>{{ number_format($queue['reserved']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4">Aucune tâche actuellement en file.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="admin-stats-grid">
                    <article class="admin-stat-card">
                        <span>Workers actifs (2 min)</span>
                        <strong>{{ $monitoring['queues']['worker_count'] ?? '—' }}</strong>
                        <small>{{ $workerStatusLabel }}</small>
                    </article>
                </div>
                <p class="admin-help-text">
                    Attente la plus ancienne :
                    {{ $monitoring['queues']['oldest_wait_seconds'] === null ? 'Aucune' : now()->subSeconds($monitoring['queues']['oldest_wait_seconds'])->diffForHumans(now(), true) }}
                    · Le heartbeat est envoyé par le worker à chaque boucle, au plus toutes les 20 secondes.
                </p>
            @endif

            <h3>Tâches échouées récentes</h3>
            @if (! $monitoring['failed_jobs']['available'])
                <p class="admin-empty-state">La table failed_jobs n'est pas disponible.</p>
            @elseif ($monitoring['failed_jobs']['recent'] === [])
                <p class="admin-empty-state">Aucune tâche échouée enregistrée.</p>
            @else
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead><tr><th>Type</th><th>File</th><th>UUID</th><th>Échec</th></tr></thead>
                        <tbody>
                            @foreach ($monitoring['failed_jobs']['recent'] as $job)
                                <tr>
                                    <td>{{ $job['job_name'] }}</td>
                                    <td>{{ $job['queue'] }}</td>
                                    <td><code>{{ $job['uuid'] }}</code></td>
                                    <td>{{ $job['failed_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="admin-help-text">La reprise générique depuis l'interface est volontairement désactivée : elle exige une vérification de l'idempotence du job concerné.</p>
            @endif
        </section>

        <section class="admin-card">
            <h2>79. Notifications et livraisons</h2>
            @if (! $monitoring['notifications']['available'])
                <p class="admin-empty-state">Le suivi des livraisons n'est pas disponible.</p>
            @else
                <p>Total des livraisons suivies : <strong>{{ number_format($monitoring['notifications']['total']) }}</strong> · Tentatives historisées : {{ $monitoring['notifications']['attempts_total'] ?? 'Non disponible' }}</p>
                <div class="admin-stats-grid">
                    @foreach ($monitoring['notifications']['by_status'] as $status => $total)
                        <article class="admin-stat-card"><span>{{ $status }}</span><strong>{{ number_format($total) }}</strong></article>
                    @endforeach
                </div>
                @if ($monitoring['notifications']['recent_failures'] !== [])
                    <h3>Dernières tentatives en échec</h3>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead><tr><th>Livraison</th><th>Canal</th><th>Tentative</th><th>État</th><th>Code</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach ($monitoring['notifications']['recent_failures'] as $attempt)
                                    <tr>
                                        <td>#{{ $attempt['delivery_id'] }}</td>
                                        <td>{{ $attempt['channel'] }}</td>
                                        <td>{{ $attempt['attempt_number'] }}</td>
                                        <td>{{ $attempt['status'] }}</td>
                                        <td>{{ $attempt['error_code'] ?? '—' }}</td>
                                        <td>{{ $attempt['started_at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <a class="btn btn-secondary" href="{{ route('admin.notifications.deliveries.index') }}">Consulter l'historique des livraisons</a>
            @endif
        </section>

        <section class="admin-card">
            <h2>80. Services de paiement</h2>
            @if (! $monitoring['payments']['available'])
                <p class="admin-empty-state">Les données de paiement ne sont pas disponibles.</p>
            @else
                <p>Paiements enregistrés : <strong>{{ number_format($monitoring['payments']['total']) }}</strong></p>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead><tr><th>Fournisseur</th><th>État</th><th>Nombre</th></tr></thead>
                        <tbody>
                            @forelse ($monitoring['payments']['by_provider'] as $provider => $statuses)
                                @foreach ($statuses as $status => $total)
                                    <tr><td>{{ $provider }}</td><td>{{ $status }}</td><td>{{ number_format($total) }}</td></tr>
                                @endforeach
                            @empty
                                <tr><td colspan="3">Aucun paiement enregistré.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="admin-help-text">
                    Transactions inconnues : {{ $monitoring['payments']['unknown_transactions'] ?? 'Non disponible' }}.
                    Événements tracés : {{ $monitoring['payments']['event_total'] ?? 'Non disponible' }}.
                    Connectivité externe : non vérifiée. Aucun appel de paiement réel n'est déclenché par cette page.
                </p>
                @if ($monitoring['payments']['recent_events'] !== [])
                    <h3>Derniers événements de paiement</h3>
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead><tr><th>Paiement</th><th>Événement</th><th>Transition</th><th>Source</th><th>Date</th></tr></thead>
                            <tbody>
                                @foreach ($monitoring['payments']['recent_events'] as $event)
                                    <tr>
                                        <td>#{{ $event['payment_id'] }}</td>
                                        <td>{{ $event['event_type'] }}</td>
                                        <td>{{ $event['from_status'] ?? '—' }} → {{ $event['to_status'] }}</td>
                                        <td>{{ $event['source'] }}</td>
                                        <td>{{ $event['occurred_at']?->format('d/m/Y H:i:s') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <a class="btn btn-secondary" href="{{ route('admin.payments.index') }}">Consulter les paiements</a>
            @endif
        </section>

        <section class="admin-card">
            <h2>81. Stockage et fichiers</h2>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead><tr><th>Disque</th><th>Accès</th><th>Capacité</th><th>Libre</th></tr></thead>
                    <tbody>
                        @foreach ($monitoring['storage']['disks'] as $disk => $data)
                            <tr>
                                <td>{{ $disk }}</td>
                                <td>{{ $data['reachable'] ? 'Accessible' : 'Non vérifié / inaccessible' }}</td>
                                <td>{{ $formatBytes($data['capacity_bytes']) }}</td>
                                <td>{{ $formatBytes($data['free_bytes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <h3>Dernier contrôle d'intégrité des fichiers</h3>
            @if (! $monitoring['storage']['integrity']['available'])
                <p class="admin-empty-state">Le contrôle d'intégrité n'est pas encore configuré.</p>
            @elseif ($monitoring['storage']['integrity']['latest'] === null)
                <p class="admin-empty-state">Aucun contrôle d'intégrité exécuté pour le moment.</p>
            @else
                @php
                    $integrity = $monitoring['storage']['integrity']['latest'];
                @endphp
                <div class="admin-stats-grid">
                    <article class="admin-stat-card"><span>Références vérifiées</span><strong>{{ number_format($integrity['records_checked']) }}</strong></article>
                    <article class="admin-stat-card"><span>Fichiers manquants</span><strong>{{ number_format($integrity['missing_files']) }}</strong></article>
                    <article class="admin-stat-card"><span>Erreurs de contrôle</span><strong>{{ number_format($integrity['check_errors']) }}</strong></article>
                </div>
                <p class="admin-help-text">État : {{ $integrity['status'] }} · Terminé le {{ $integrity['completed_at'] }}</p>
            @endif
            <p class="admin-help-text">La capacité système est mesurable pour les disques locaux. Le contrôle vérifie les références connues sans afficher les chemins privés ni supprimer automatiquement de fichiers.</p>
        </section>

        <section class="admin-card">
            <h2>82. Erreurs applicatives</h2>
            @if (! $monitoring['errors']['available'])
                <p class="admin-empty-state">Aucun fichier de journal lisible n'a été détecté.</p>
            @else
                <dl class="admin-details-list">
                    <div><dt>Dernier fichier</dt><dd>{{ $monitoring['errors']['file_name'] }}</dd></div>
                    <div><dt>Taille</dt><dd>{{ $formatBytes($monitoring['errors']['size_bytes']) }}</dd></div>
                    <div><dt>Dernière écriture</dt><dd>{{ $monitoring['errors']['last_written_at'] }}</dd></div>
                </dl>
                <p class="admin-help-text">Les journaux bruts ne sont pas exposés dans l'administration. Les incidents regroupés sont présentés dans la section suivante, sans message ni trace complète.</p>
            @endif
        </section>

        <section class="admin-card">
            <h2>Incidents applicatifs agrégés</h2>
            @if (! $monitoring['incidents']['available'])
                <p class="admin-empty-state">La collecte des incidents n'est pas encore disponible.</p>
            @elseif ($monitoring['incidents']['recent'] === [])
                <p class="admin-empty-state">Aucun incident applicatif agrégé.</p>
            @else
                <p>Groupes d'incidents observés : <strong>{{ number_format($monitoring['incidents']['total']) }}</strong></p>
                <div class="admin-table-wrapper">
                    <table class="admin-table">
                        <thead><tr><th>Type</th><th>Point technique</th><th>Occurrences</th><th>Première occurrence</th><th>Dernière occurrence</th></tr></thead>
                        <tbody>
                            @foreach ($monitoring['incidents']['recent'] as $incident)
                                <tr>
                                    <td>{{ $incident['exception_type'] }}</td>
                                    <td>{{ $incident['location_hint'] }}</td>
                                    <td>{{ number_format($incident['occurrence_count']) }}</td>
                                    <td>{{ $incident['first_seen_at'] }}</td>
                                    <td>{{ $incident['last_seen_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="admin-help-text">Les messages, traces, URL complètes, données de requête et identifiants personnels ne sont pas stockés dans ce registre.</p>
            @endif
        </section>

        <section class="admin-card">
            <h2>83. Performance et disponibilité</h2>
            <div class="admin-stats-grid">
                <article class="admin-stat-card">
                    <span>Base de données</span>
                    <strong>{{ $monitoring['application']['database_latency_ms'] === null ? '—' : $monitoring['application']['database_latency_ms'].' ms' }}</strong>
                    <small>Durée du contrôle SQL ponctuel</small>
                </article>
                <article class="admin-stat-card">
                    <span>Requêtes HTTP (1 h)</span>
                    <strong>{{ number_format($monitoring['performance']['request_count_last_hour']) }}</strong>
                    <small>Toutes routes hors contrôle /up</small>
                </article>
                <article class="admin-stat-card">
                    <span>Taux d'erreur HTTP</span>
                    <strong>{{ $monitoring['performance']['http_error_rate'] === null ? '—' : number_format($monitoring['performance']['http_error_rate'], 2, ',', ' ').' %' }}</strong>
                    <small>Réponses 5xx agrégées</small>
                </article>
                <article class="admin-stat-card">
                    <span>Temps moyen HTTP</span>
                    <strong>{{ $monitoring['performance']['average_response_ms'] === null ? '—' : $monitoring['performance']['average_response_ms'].' ms' }}</strong>
                    <small>Sur les minutes archivées</small>
                </article>
                <article class="admin-stat-card">
                    <span>Classe P95 récente</span>
                    <strong>{{ $monitoring['performance']['p95_bucket_ms'] === null ? '—' : ($monitoring['performance']['p95_bucket_ms'] > 1000 ? '> 1 000 ms' : '≤ '.$monitoring['performance']['p95_bucket_ms'].' ms') }}</strong>
                    <small>Classe histogramme de la dernière minute</small>
                </article>
                <article class="admin-stat-card">
                    <span>Disponibilité historique</span>
                    <strong>Non mesurée</strong>
                    <small>Contrôle externe requis</small>
                </article>
            </div>
            <p class="admin-help-text">{{ $monitoring['performance']['message'] }}</p>
            <dl class="admin-details-list">
                <div><dt>Laravel</dt><dd>{{ $monitoring['application']['laravel'] }}</dd></div>
                <div><dt>PHP</dt><dd>{{ $monitoring['application']['php'] }}</dd></div>
                <div><dt>Environnement</dt><dd>{{ $monitoring['application']['environment'] }}</dd></div>
            </dl>
        </section>
    </div>
@endsection
