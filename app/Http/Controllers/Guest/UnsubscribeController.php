<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lien signé (comme les pages de modification/annulation d'inscription,
 * T-033) : la signature protège {organization}/{contact} contre toute
 * manipulation, jamais authentifié — un invité n'a pas de session.
 */
final class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, int $organization, int $contact): View
    {
        $organizationModel = Organization::query()->findOrFail($organization);
        app(CurrentOrganization::class)->set($organizationModel);

        $contactModel = Contact::query()->findOrFail($contact);

        if ($contactModel->unsubscribed_at === null) {
            $contactModel->update(['unsubscribed_at' => now()]);
        }

        return view('guest.unsubscribe', ['organization' => $organizationModel]);
    }
}
