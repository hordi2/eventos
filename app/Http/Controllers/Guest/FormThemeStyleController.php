<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Support\CustomCss;
use App\Domain\Form\Support\EventForms;
use App\Domain\Form\Support\FormSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CSS personnalisé du parcours invité, servi comme une vraie feuille de style
 * plutôt qu'en ligne : le navigateur la garde d'un écran à l'autre du
 * parcours (accueil, identité, réponses, récapitulatif), et une future CSP
 * stricte n'a pas à autoriser du style en ligne pour l'accepter. L'adresse
 * porte une empreinte du contenu, d'où la longue durée de cache.
 */
final class FormThemeStyleController extends Controller
{
    public function show(Request $request): Response
    {
        /** @var Event $event */
        $event = $request->attributes->get('guestEvent');
        $slug = $request->query('formulaire');
        $form = app(EventForms::class)->forLink($event->id, is_string($slug) ? $slug : null);
        $css = CustomCss::forDelivery(FormSettings::resolve($form?->settings)['theme']['custom_css']);

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
