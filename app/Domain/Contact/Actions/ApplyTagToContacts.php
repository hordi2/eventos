<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Tag;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Application en masse d'un tag sur un segment (T-042 : « Application d'un
 * tag en masse sur un segment ») — le segment lui-même est recalculé à la
 * volée par ComputeEventSegmentContacts, cette action se contente de tagger
 * les contacts qu'on lui donne.
 */
final class ApplyTagToContacts
{
    /**
     * @param  list<int>  $contactIds
     */
    public function handle(Organization $organization, User $actor, Tag $tag, array $contactIds): int
    {
        Gate::forUser($actor)->authorize('updateGuests', $organization);

        if ($contactIds === []) {
            return 0;
        }

        $now = CarbonImmutable::now();

        // upsert plutôt que attach() : ré-appliquer un tag déjà présent sur
        // certains contacts du segment ne doit jamais échouer sur la
        // contrainte unique (contact_id, tag_id). Un $update vide ferait
        // tomber Laravel sur un insert() nu (voir Builder::upsert()), qui
        // échouerait sur ce même conflit — d'où cette colonne mise à jour
        // sur elle-même, en no-op délibéré, pour obtenir un vrai
        // "ON CONFLICT DO NOTHING".
        return DB::table('contact_tag')->upsert(
            array_map(fn (int $contactId): array => [
                'organization_id' => $organization->id,
                'contact_id' => $contactId,
                'tag_id' => $tag->id,
                'created_at' => $now,
            ], $contactIds),
            ['contact_id', 'tag_id'],
            ['tag_id'],
        );
    }
}
