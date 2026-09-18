<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Actions\AcceptSenderAgreement;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SenderAgreementController extends Controller
{
    public function store(Request $request, AcceptSenderAgreement $acceptSenderAgreement): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $acceptSenderAgreement->handle(Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId()), $user);

        return back()->with('status', 'sender-agreement-accepted');
    }
}
