<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Models\Membership;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sélecteur d'espace de travail du menu utilisateur. ResolveCurrentOrganization
 * revérifie l'adhésion à chaque requête : la session ne fait que mémoriser
 * le choix.
 */
final class SwitchOrganizationController extends Controller
{
    public function __invoke(Request $request, int $organization): RedirectResponse
    {
        $isMember = Membership::query()
            ->where('user_id', $request->user()?->id)
            ->where('organization_id', $organization)
            ->exists();

        abort_unless($isMember, 404);

        $request->session()->put('current_organization_id', $organization);

        return redirect()->route('dashboard');
    }
}
