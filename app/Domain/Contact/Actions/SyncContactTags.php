<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;

/**
 * sync() seul n'écrit pas organization_id sur les lignes de contact_tag
 * qu'il insère : la RLS (contrainte NOT NULL + WITH CHECK) rejetterait
 * l'écriture sans ce détail.
 */
final class SyncContactTags
{
    /**
     * @param  list<int>  $tagIds
     */
    public function handle(Contact $contact, array $tagIds): void
    {
        $contact->tags()->sync(array_fill_keys($tagIds, ['organization_id' => $contact->organization_id]));
    }
}
