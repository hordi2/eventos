<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Data\FormVisibilityContext;

/**
 * Traverse Domain/Contact et Domain/Form, d'où sa place dans Support : les
 * tags du contact de l'invité, retrouvé par son adresse e-mail, décident des
 * questions « Seulement pour les invités portant le tag… ». Un invité
 * inconnu de l'organisation n'a aucun tag.
 */
final class BuildGuestVisibilityContext
{
    public function handle(int $organizationId, ?string $email, bool $attending): FormVisibilityContext
    {
        $email = mb_strtolower(trim((string) $email));

        $contact = $email === ''
            ? null
            : Contact::query()->where('organization_id', $organizationId)->where('email', $email)->first();

        $tagIds = $contact?->tags()->pluck('tags.id')->map(fn (mixed $id): int => (int) $id)->values()->all() ?? [];

        return new FormVisibilityContext($attending, $tagIds);
    }
}
