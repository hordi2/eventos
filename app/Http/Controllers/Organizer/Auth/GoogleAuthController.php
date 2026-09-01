<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\AuthenticateViaGoogle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

final class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(AuthenticateViaGoogle $authenticateViaGoogle): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = $authenticateViaGoogle->handle($googleUser);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
