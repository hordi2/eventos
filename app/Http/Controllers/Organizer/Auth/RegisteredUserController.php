<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'referralCode' => $request->query('via'),
        ]);
    }

    public function store(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('organization_name')->toString(),
            $request->string('referral_code')->toString() ?: null,
        );

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
