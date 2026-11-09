@extends('layouts.admin')

@section('page_eyebrow','ADMINISTRATION / CONTRÔLE')
@section('page_title','Vérification professionnelle')
@section('page_description','Suivez les dossiers, examinez les justificatifs privés et prenez des décisions traçables.')
@section('page_actions')<span class="admin-page-badge"><i class="fa-solid fa-user-shield"></i> Centre de confiance</span>@endsection

@section('content')
<div class="admin-stat-grid verification-metrics">
    @foreach([
        ['pending','En attente'],
        ['under_review','En cours de revue'],
        ['additional_documents_requested','Documents demandés'],
        ['documents_pending','Justificatifs à examiner'],
        ['verified','Vérifiés'],
        ['rejected','Rejetés'],
    ] as $metric)
        <article class="admin-stat">
            <span>{{ $metric[1] }}</span>
            <strong>{{ number_format((int) ($metrics[$metric[0]] ?? 0), 0, ',', ' ') }}</strong>
        </article>
    @endforeach
</div>

<section class="admin-card">
    <div class="admin-card__header">
        <div><span class="admin-card__eyebrow">File de traitement</span><h3>{{ $professionals->total() }} dossier(s)</h3></div>
    </div>
    <form method="GET" class="admin-filter-grid">
        <label class="admin-field admin-field--wide"><span>Recherche</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nom, e-mail ou titre professionnel"></label>
        <label class="admin-field"><span>État de vérification</span><select name="verification_status">
            <option value="">Tous les états</option>
            @foreach(['pending'=>'En attente','under_review'=>'En revue','additional_documents_requested'=>'Documents complémentaires demandés','verified'=>'Vérifié','rejected'=>'Rejeté'] as $value=>$label)
                <option value="{{ $value }}" @selected(($filters['verification_status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select></label>
        <label class="admin-field"><span>État du compte</span><select name="account_status">
            <option value="">Tous</option><option value="active" @selected(($filters['account_status'] ?? '') === 'active')>Actif</option><option value="suspended" @selected(($filters['account_status'] ?? '') === 'suspended')>Suspendu</option>
        </select></label>
        <div class="admin-filter-actions"><button class="button button--primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button><a class="button button--secondary" href="{{ route('admin.verification.index') }}">Réinitialiser</a></div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Professionnel</th><th>Vérification</th><th>Compte</th><th>Justificatifs</th><th>Création du profil</th><th></th></tr></thead>
        <tbody>
        @forelse($professionals as $professional)
            @php($status = $professional->verification_status?->value)
            <tr>
                <td><a class="admin-user-cell" href="{{ route('admin.verification.show',$professional) }}"><span class="admin-avatar">{{ mb_strtoupper(mb_substr($professional->user->name ?? '?',0,1)) }}</span><span><strong>{{ $professional->user->name }}</strong><small>{{ $professional->professional_title ?: $professional->user->email }}</small></span></a></td>
                <td><span class="admin-status admin-status--{{ $status }}">{{ ['pending'=>'En attente','under_review'=>'En revue','additional_documents_requested'=>'Documents demandés','verified'=>'Vérifié','rejected'=>'Rejeté'][$status] ?? '—' }}</span></td>
                <td>{{ $professional->user->account_status?->value === 'active' ? 'Actif' : 'Suspendu' }}</td>
                <td>{{ $professional->documents_count }}</td>
                <td>{{ $professional->created_at?->format('d/m/Y') }}</td>
                <td><a class="admin-icon-button" href="{{ route('admin.verification.show',$professional) }}" aria-label="Ouvrir le dossier"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="admin-empty"><i class="fa-solid fa-user-check"></i><strong>Aucun dossier</strong><span>Aucun professionnel ne correspond aux filtres.</span></div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($professionals->hasPages())<div class="admin-pagination">{{ $professionals->links() }}</div>@endif
</section>
@endsection
