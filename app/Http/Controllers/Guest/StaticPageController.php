<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Organization\Actions\GetProcessingRegister;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Pages statiques du pied de page (support, retour d'information,
 * conditions d'utilisation, confidentialité, affiliation) — publiques,
 * jamais d'authentification, comme /status (T-076). « Communauté » n'en
 * fait plus partie depuis que c'est un vrai forum (CommunityController,
 * authentification requise).
 */
final class StaticPageController extends Controller
{
    public function support(): View
    {
        return view('guest.static.support');
    }

    public function feedback(): View
    {
        return view('guest.static.feedback');
    }

    public function terms(): View
    {
        return view('guest.static.terms');
    }

    public function privacy(GetProcessingRegister $getProcessingRegister): View
    {
        return view('guest.static.privacy', ['activities' => $getProcessingRegister->handle()]);
    }

    public function affiliates(): View
    {
        return view('guest.static.affiliates');
    }
}
