<?php

declare(strict_types=1);

namespace Tests\Feature\Frontend;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class SharedComponentsTest extends TestCase
{
    public function test_button_component_renders_semantic_button_with_variant_and_icon(): void
    {
        $html = Blade::render(
            '<x-button type="submit" variant="primary" icon="fa-solid fa-check">Enregistrer</x-button>'
        );

        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('button--primary', $html);
        $this->assertStringContainsString('fa-solid fa-check', $html);
        $this->assertStringContainsString('Enregistrer', $html);
    }

    public function test_card_component_supports_named_header_and_footer_slots(): void
    {
        $html = Blade::render(
            <<<'BLADE'
            <x-card>
                <x-slot:header><h2>Titre</h2></x-slot:header>
                Contenu principal
                <x-slot:footer>Actions</x-slot:footer>
            </x-card>
            BLADE
        );

        $this->assertStringContainsString('surface-card__header', $html);
        $this->assertStringContainsString('Titre', $html);
        $this->assertStringContainsString('Contenu principal', $html);
        $this->assertStringContainsString('surface-card__footer', $html);
        $this->assertStringContainsString('Actions', $html);
    }

    public function test_badge_component_renders_semantic_status_variant(): void
    {
        $html = Blade::render(
            '<x-badge variant="success" dot>Vérifié</x-badge>'
        );

        $this->assertStringContainsString('badge--success', $html);
        $this->assertStringContainsString('badge__dot', $html);
        $this->assertStringContainsString('Vérifié', $html);
    }

    public function test_alert_component_has_accessible_alert_role_and_dismiss_control(): void
    {
        $html = Blade::render(
            '<x-alert type="warning" title="Attention" dismissible>Action requise.</x-alert>'
        );

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('alert--warning', $html);
        $this->assertStringContainsString('Attention', $html);
        $this->assertStringContainsString('data-alert-dismiss', $html);
    }

    public function test_input_component_exposes_accessible_label_and_help_text(): void
    {
        $html = Blade::render(
            '<x-input name="email" label="Adresse e-mail" type="email" help="Utilisez une adresse valide." />',
            ['errors' => new ViewErrorBag()]
        );

        $this->assertStringContainsString('for="email"', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Adresse e-mail', $html);
        $this->assertStringContainsString('Utilisez une adresse valide.', $html);
    }

    public function test_password_input_includes_accessible_visibility_control(): void
    {
        $html = Blade::render(
            '<x-input name="password" label="Mot de passe" type="password" autocomplete="current-password" />',
            ['errors' => new ViewErrorBag()]
        );

        $this->assertStringContainsString('data-password-toggle="password"', $html);
        $this->assertStringContainsString('aria-label="Afficher le mot de passe"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);
    }

    public function test_empty_state_and_loading_components_provide_status_structure(): void
    {
        $loading = Blade::render('<x-loading label="Chargement des professionnels…" />');
        $empty = Blade::render(
            '<x-empty-state title="Aucun professionnel" description="Aucun résultat ne correspond à vos critères." />'
        );

        $this->assertStringContainsString('role="status"', $loading);
        $this->assertStringContainsString('Chargement des professionnels…', $loading);
        $this->assertStringContainsString('state-empty', $empty);
        $this->assertStringContainsString('Aucun professionnel', $empty);
        $this->assertStringContainsString('Aucun résultat ne correspond à vos critères.', $empty);
    }
}
