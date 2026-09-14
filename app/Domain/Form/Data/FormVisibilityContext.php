<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

/**
 * Ce que l'on sait de l'invité au moment d'afficher ou de valider ses
 * réponses : s'il vient, et quels tags porte son contact. Sert les réglages
 * « Demander si » (config.show_if) et « Seulement pour les invités portant
 * le tag… » (config.tag_ids) d'un champ.
 */
final class FormVisibilityContext
{
    /**
     * @param  list<int>|null  $tagIds  null quand les tags ne sont pas connus (aperçu de l'organisateur) : le critère de tag n'est alors pas appliqué
     */
    public function __construct(
        public readonly bool $attending = true,
        public readonly ?array $tagIds = null,
    ) {}
}
