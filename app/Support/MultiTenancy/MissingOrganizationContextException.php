<?php

declare(strict_types=1);

namespace App\Support\MultiTenancy;

use RuntimeException;

final class MissingOrganizationContextException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            "Aucune organisation courante n'est définie : impossible d'accéder à une donnée cloisonnée par organisation."
        );
    }
}
