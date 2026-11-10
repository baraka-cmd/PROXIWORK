@extends('layouts.admin')

@section('title', 'Configuration générale')

@section('content')
    <div class="page-header">
        <div>
            <p class="page-eyebrow">CONFIGURATION DE LA PLATEFORME</p>
            <h1>Paramètres généraux</h1>
            <p class="page-description">Paramètres opérationnels contrôlés côté serveur. Les secrets et les règles financières historiques ne sont pas modifiables ici.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <p>Certains paramètres sont invalides.</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" class="admin-settings-form">
        @csrf
        @method('PUT')

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Identité et contact</h2><p>Informations publiques générales.</p></div></div>
            <div class="form-grid">
                <label class="form-field"><span>Nom de la plateforme</span><input name="settings[platform][name]" maxlength="120" required value="{{ old('settings.platform.name', $settings['platform.name']) }}"></label>
                <label class="form-field"><span>E-mail de support</span><input type="email" name="settings[platform][support_email]" maxlength="190" value="{{ old('settings.platform.support_email', $settings['platform.support_email']) }}"></label>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Commissions</h2><p>Chaque modification crée une nouvelle version. Les commandes déjà créées conservent le taux capturé au moment de leur acceptation.</p></div></div>
            <label class="form-field"><span>Taux global de commission (%)</span><input type="number" name="settings[commission][rate]" min="0.01" max="99.99" step="0.01" value="{{ old('settings.commission.rate', $settings['commission.rate']) }}" required></label>
        </section>

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Publication des services</h2><p>Ces seuils complètent les contrôles obligatoires de vérification, de modération et d'éligibilité.</p></div></div>
            <div class="form-grid">
                <label class="form-field"><span>Description minimale (caractères)</span><input type="number" name="settings[publication][minimum_description_length]" min="20" max="1000" value="{{ old('settings.publication.minimum_description_length', $settings['publication.minimum_description_length']) }}" required></label>
                <label class="form-field"><span>Titre maximal (caractères)</span><input type="number" name="settings[publication][maximum_title_length]" min="3" max="160" value="{{ old('settings.publication.maximum_title_length', $settings['publication.maximum_title_length']) }}" required></label>
                <label class="form-field"><span>Images maximales par service</span><input type="number" name="settings[publication][maximum_images]" min="1" max="8" value="{{ old('settings.publication.maximum_images', $settings['publication.maximum_images']) }}" required></label>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Fichiers et médias</h2><p>Les documents de vérification restent privés ; les images publiques ne rendent jamais les documents privés accessibles.</p></div></div>
            <div class="form-grid">
                <label class="form-field"><span>Image maximale (Ko)</span><input type="number" name="settings[media][maximum_image_kb]" min="100" max="10240" value="{{ old('settings.media.maximum_image_kb', $settings['media.maximum_image_kb']) }}" required></label>
                <label class="form-field"><span>Document maximal (Ko)</span><input type="number" name="settings[media][maximum_document_kb]" min="100" max="20480" value="{{ old('settings.media.maximum_document_kb', $settings['media.maximum_document_kb']) }}" required></label>
            </div>
        </section>

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Vérification professionnelle</h2><p>Les exigences documentaires supplémentaires sont globales. La revue humaine et les transitions existantes restent obligatoires.</p></div></div>
            <div class="form-grid">
                @foreach (['identity' => 'Pièce d’identité', 'certificate' => 'Certificat professionnel', 'training' => 'Attestation de formation', 'license' => 'Licence ou autorisation'] as $documentType => $label)
                    <label class="form-field form-field--checkbox">
                        <input type="hidden" name="settings[verification][require_{{ $documentType }}]" value="0">
                        <input type="checkbox" name="settings[verification][require_{{ $documentType }}]" value="1" @checked(old('settings.verification.require_'.$documentType, $settings['verification.require_'.$documentType]))>
                        <span>Exiger : {{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="dashboard-card">
            <div class="dashboard-card__header"><div><h2>Canaux de notification</h2><p>Les événements critiques continuent d'être traités même si un canal facultatif est désactivé.</p></div></div>
            <div class="form-grid">
                @foreach (['email' => 'E-mail', 'sms' => 'SMS', 'push' => 'Push'] as $channel => $label)
                    <label class="form-field form-field--checkbox">
                        <input type="hidden" name="settings[notifications][{{ $channel }}_enabled]" value="0">
                        <input type="checkbox" name="settings[notifications][{{ $channel }}_enabled]" value="1" @checked(old('settings.notifications.'.$channel.'_enabled', $settings['notifications.'.$channel.'_enabled']))>
                        <span>{{ $label }} activé globalement</span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="form-actions"><button type="submit" class="button button-primary">Enregistrer les paramètres</button></div>
    </form>

    <section class="dashboard-card">
        <div class="dashboard-card__header"><div><h2>Devises et vérification</h2><p>Les devises rencontrées dans le projet sont {{ implode(' et ', $currencies) }}. Le workflow de vérification existant reste {{ implode(' → ', $verificationStates) }}. Les règles de paiement et les décisions de vérification ne peuvent pas être contournées depuis cette page.</p></div></div>
    </section>

    <section class="dashboard-card">
        <div class="dashboard-card__header"><div><h2>État des intégrations</h2><p>Les secrets restent exclusivement dans la configuration d'environnement. Cet indicateur ne réalise pas de transaction réelle avec les fournisseurs.</p></div></div>
        <div class="settings-integration-list">
            @foreach ($integrations as $name => $integration)
                <div class="settings-integration-row">
                    <div><strong>{{ $name }}</strong><p>{{ $integration['detail'] }}</p></div>
                    <span class="status-badge {{ $integration['configured'] ? 'status-badge--success' : 'status-badge--warning' }}">{{ $integration['configured'] ? 'Paramètres présents' : 'Non configuré ou incomplet' }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
