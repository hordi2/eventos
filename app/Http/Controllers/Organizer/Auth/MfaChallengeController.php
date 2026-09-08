<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\ResendMfaCode;
use App\Domain\Organization\Actions\VerifyMfaCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Auth\VerifyMfaCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MfaChallengeController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('mfa_pending_user_id') === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/VerifyMfaCode');
    }

    public function store(VerifyMfaCodeRequest $request, VerifyMfaCode $action): RedirectResponse
    {
        $action->handle($request->string('code')->toString(), $request->ip() ?? '');

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request, ResendMfaCode $action): RedirectResponse
    {
        $action->handle();

        return back()->with('status', 'mfa-code-resent');
    }
}
