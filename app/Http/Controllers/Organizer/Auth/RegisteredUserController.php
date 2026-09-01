<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Auth;

use App\Domain\Organization\Actions\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, RegisterUser $registerUser): RedirectResponse
    {
        $user = $registerUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('organization_name')->toString(),
        );

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
