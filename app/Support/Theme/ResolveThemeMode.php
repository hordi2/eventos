<?php

declare(strict_types=1);

namespace App\Support\Theme;

use App\Domain\Organization\Models\Organization;
use App\Domain\Organization\Models\ThemeMode;
use App\Support\MultiTenancy\CurrentOrganization;

/**
 * Mode d'affichage de l'espace organisateur (page Paramètres →
 * Personnalisation) — utilisé par resources/views/app.blade.php pour poser
 * `data-theme` sur `<html>` avant tout rendu React, afin d'éviter un flash
 * du mauvais thème. « Normal » par défaut, y compris tant qu'aucune
 * organisation n'est résolue (connexion, inscription) : le mode sombre ne
 * suit plus la préférence système, seul ce réglage explicite compte.
 */
final class ResolveThemeMode
{
    public function handle(): ThemeMode
    {
        $organizationId = app(CurrentOrganization::class)->id();

        if ($organizationId === null) {
            return ThemeMode::Light;
        }

        return Organization::query()->find($organizationId)->theme_mode ?? ThemeMode::Light;
    }
}
