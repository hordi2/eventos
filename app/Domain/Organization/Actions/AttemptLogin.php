<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AttemptLogin
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(string $email, string $password, string $ip, bool $remember = false): void
    {
        $key = $this->throttleKey($email, $ip);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Trop de tentatives de connexion. Réessayez dans :minutes minutes.', [
                    'minutes' => (int) ceil($this->limiter->availableIn($key) / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            $this->limiter->hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('Ces identifiants ne correspondent à aucun compte.'),
            ]);
        }

        $this->limiter->clear($key);

        /** @var User $user */
        $user = Auth::user();

        // Aucun contexte d'organisation n'est encore résolu à ce stade (le
        // middleware resolve-organization n'a pas encore tourné) : on
        // rattache la connexion à la première organisation de l'utilisateur
        // pour qu'elle apparaisse dans son journal d'audit.
        $organizationId = $user->memberships()->value('organization_id');

        $this->recordAuditLog->handle(action: 'auth.login', causer: $user, organizationId: $organizationId);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
