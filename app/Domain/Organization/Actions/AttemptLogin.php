<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Organization;
use App\Mail\MfaCodeMail;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Paramètres → Sécurité (demande utilisateur) : une fois les identifiants
 * vérifiés, si l'utilisateur ou l'une de ses organisations exige la MFA par
 * e-mail, la connexion est immédiatement annulée (Auth::logout) et un code
 * à usage unique est envoyé — la session n'est établie qu'après vérification
 * du code (voir VerifyMfaCode). Le défi en cours vit en session (Redis,
 * §2 CLAUDE.md), jamais en base : pas de table à purger, il expire avec la
 * session comme n'importe quelle donnée temporaire de connexion.
 */
final class AttemptLogin
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    private const MFA_CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly Session $session,
    ) {}

    /**
     * @return bool true si une vérification MFA est désormais requise (la
     *              session n'est pas encore authentifiée), false si la
     *              connexion est complète.
     */
    public function handle(string $email, string $password, string $ip, bool $remember = false): bool
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

        if ($this->requiresMfa($user)) {
            Auth::logout();
            $this->issueChallenge($user, $remember);

            return true;
        }

        $this->recordSuccessfulLogin($user);

        return false;
    }

    private function requiresMfa(User $user): bool
    {
        if ($user->mfa_email_enabled) {
            return true;
        }

        return Organization::query()
            ->whereIn('id', $user->memberships()->pluck('organization_id'))
            ->where('require_mfa_for_members', true)
            ->exists();
    }

    private function issueChallenge(User $user, bool $remember): void
    {
        $code = (string) random_int(100000, 999999);

        $this->session->put('mfa_pending_user_id', $user->id);
        $this->session->put('mfa_pending_remember', $remember);
        $this->session->put('mfa_code_hash', Hash::make($code));
        $this->session->put('mfa_expires_at', now()->addMinutes(self::MFA_CODE_TTL_MINUTES)->toIso8601String());

        Mail::to($user->email)->queue(new MfaCodeMail($code, self::MFA_CODE_TTL_MINUTES));
    }

    private function recordSuccessfulLogin(User $user): void
    {
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
