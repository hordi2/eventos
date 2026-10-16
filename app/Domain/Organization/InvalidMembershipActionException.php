<?php

declare(strict_types=1);

namespace App\Domain\Organization;

use RuntimeException;

final class InvalidMembershipActionException extends RuntimeException
{
    public static function cannotRemoveOwner(): self
    {
        return new self('Un propriétaire ne se retire pas depuis cette liste.');
    }

    public static function alreadyMember(string $email): self
    {
        return new self("{$email} est déjà membre de votre organisation : modifiez son rôle dans la liste plutôt que de l'inviter à nouveau.");
    }

    public static function cannotInviteAsOwner(): self
    {
        return new self('Le rôle propriétaire ne se distribue pas par invitation.');
    }

    public static function cannotSetOwnerRole(): self
    {
        return new self('Le rôle propriétaire ne se change pas depuis cette liste.');
    }
}
