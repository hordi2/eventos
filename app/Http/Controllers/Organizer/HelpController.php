<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Guide de démarrage, tutoriels des parcours principaux et FAQ (T-077).
 * Contenu statique rédigé directement dans la page React — aucune donnée
 * par organisation, aucune raison d'ajouter un CMS pour ce périmètre MVP
 * (décision prise avec l'utilisateur).
 */
final class HelpController extends Controller
{
    public function index(): InertiaResponse
    {
        return Inertia::render('Help/Index');
    }
}
