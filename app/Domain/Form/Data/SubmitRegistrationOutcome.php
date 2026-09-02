<?php

declare(strict_types=1);

namespace App\Domain\Form\Data;

enum SubmitRegistrationOutcome: string
{
    case Created = 'created';
    case DuplicateFound = 'duplicate_found';
}
