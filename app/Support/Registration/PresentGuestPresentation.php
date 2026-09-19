<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Models\Form;
use App\Domain\Form\Support\CustomCss;
use App\Domain\Form\Support\FormSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Réglages d'écrans et thème d'un formulaire, pour les vues du parcours
 * invité et la mise en page invitée — partagés avec l'aperçu du constructeur,
 * pour que l'organisateur voie exactement ce que verront ses invités.
 */
final class PresentGuestPresentation
{
    /**
     * @return array{settings: array<string, array<string, mixed>>, formTheme: array{variables: array<string, string>, logoUrl: ?string, backgroundUrl: ?string, headerUrl: ?string, customCssUrl: ?string}}
     */
    public function handle(Event $event, ?Form $form): array
    {
        $settings = FormSettings::resolve($form?->settings);
        $theme = $settings['theme'];
        $customCss = CustomCss::forDelivery($theme['custom_css']);

        return [
            'settings' => $settings,
            'formTheme' => [
                'variables' => FormSettings::cssVariables($settings),
                'logoUrl' => $this->url($theme['logo_path']),
                'backgroundUrl' => $this->url($theme['background_image_path']),
                'headerUrl' => $this->url($theme['header_image_path']),
                // L'empreinte du contenu sert de version : la feuille est mise
                // en cache longtemps, mais une modification arrive tout de suite.
                'customCssUrl' => $customCss === '' ? null : route('guest.registration.theme-style', [
                    $event->organization->slug,
                    $event->slug,
                    ...($form === null || $form->is_default ? [] : ['formulaire' => $form->slug]),
                    'v' => substr(sha1($customCss), 0, 8),
                ]),
            ],
        ];
    }

    private function url(mixed $path): ?string
    {
        return is_string($path) ? Storage::disk('public')->url($path) : null;
    }
}
