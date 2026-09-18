<?php

declare(strict_types=1);

namespace App\Support\Registration;

use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Data\FormVisibilityContext;

/**
 * Traverse Domain/Contact et Domain/Form, d'où sa place dans Support : les
 * tags du contact de l'invité décident des questions « Seulement pour les
 * invités portant le tag… ». Le contact est pris tel quel quand il est connu
 * (invitation, inscription reliée) — un invité connu par son seul numéro
 * WhatsApp n'a pas d'e-mail —, sinon retrouvé par l'adresse e-mail. Un
 * invité inconnu de l'organisation n'a aucun tag.
 */
final class BuildGuestVisibilityContext
{
    public function handle(int $organizationId, ?string $email, bool $attending, ?int $contactId = null): FormVisibilityContext
    {
        $email = mb_strtolower(trim((string) $email));

        $contact = match (true) {
            $contactId !== null => Contact::query()->where('organization_id', $organizationId)->find($contactId),
            $email !== '' => Contact::query()->where('organization_id', $organizationId)->where('email', $email)->first(),
            default => null,
        };

        $tagIds = $contact?->tags()->pluck('tags.id')->map(fn (mixed $id): int => (int) $id)->values()->all() ?? [];

        return new FormVisibilityContext($attending, $tagIds);
    }
}
