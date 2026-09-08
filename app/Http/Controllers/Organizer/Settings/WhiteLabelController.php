<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class WhiteLabelController extends Controller
{
    public function edit(): Response
    {
        $organization = $this->currentOrganization();

        return Inertia::render('Settings/WhiteLabel', [
            'emailFromName' => $organization->email_from_name,
            'emailReplyTo' => $organization->email_reply_to,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'email_from_name' => ['nullable', 'string', 'max:255'],
            'email_reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        $this->currentOrganization()->update([
            'email_from_name' => $request->string('email_from_name')->toString() ?: null,
            'email_reply_to' => $request->string('email_reply_to')->toString() ?: null,
        ]);

        return back()->with('status', 'white-label-updated');
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
