<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

/**
 * Authentification des postes/applications de check-in (T-060) : jeton
 * Sanctum par appareil, jamais de session — même limitation de tentatives
 * que la connexion organisateur (AttemptLogin) pour rester cohérent avec la
 * règle "rate limiting obligatoire sur le login" (section 7 du CLAUDE.md).
 */
final class IssueCheckInApiToken
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function handle(string $email, string $password, string $deviceName, string $ip): NewAccessToken
    {
        $key = $this->throttleKey($email, $ip);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Trop de tentatives de connexion. Réessayez dans :minutes minutes.', [
                    'minutes' => (int) ceil($this->limiter->availableIn($key) / 60),
                ]),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->limiter->hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('Ces identifiants ne correspondent à aucun compte.'),
            ]);
        }

        $this->limiter->clear($key);

        $organizationId = $user->memberships()->value('organization_id');

        $this->recordAuditLog->handle(action: 'auth.check_in_api_login', causer: $user, organizationId: $organizationId);

        return $user->createToken($deviceName);
    }

    private function throttleKey(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
