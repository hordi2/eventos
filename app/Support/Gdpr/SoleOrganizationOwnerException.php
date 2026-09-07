<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use RuntimeException;

final class SoleOrganizationOwnerException extends RuntimeException
{
    public static function soleOwner(): self
    {
        return new self(
            "Vous êtes seul(e) propriétaire d'au moins une organisation. ".
            'Transférez la propriété à un autre membre ou supprimez cette organisation avant de supprimer votre compte.',
        );
    }
}
