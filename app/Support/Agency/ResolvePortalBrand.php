<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Domain\Organization\Models\Organization;
use Illuminate\Support\Facades\Storage;

/**
 * La marque du portail d'un compte client (D10, white-label du §M6.3).
 *
 * Un compte confié à une agence qui a choisi de marquer les portails de ses
 * clients affiche son logo et sa couleur, à la place de ceux d'Itaza. Partout
 * ailleurs — organisation indépendante, agence qui ne le demande pas, agence
 * sans logo —, rien n'est renvoyé et le portail garde sa marque d'origine.
 *
 * Traverse Organization seul, mais sert les mêmes règles que le portefeuille :
 * sa place est auprès de lui.
 */
final class ResolvePortalBrand
{
    /**
     * @return array{name: string, logoUrl: string, primaryColor: ?string}|null
     */
    public function handle(?Organization $organization): ?array
    {
        if ($organization === null || $organization->managed_by_organization_id === null) {
            return null;
        }

        $agency = Organization::query()->find($organization->managed_by_organization_id);

        if ($agency === null || ! $agency->brands_client_portals || $agency->logo_path === null) {
            return null;
        }

        return [
            'name' => $agency->name,
            'logoUrl' => Storage::disk('public')->url($agency->logo_path),
            'primaryColor' => $agency->primary_color,
        ];
    }
}
