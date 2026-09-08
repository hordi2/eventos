<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class VerifyMfaCode
{
    private const MAX_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 900;

    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly RecordAuditLog $recordAuditLog,
        private readonly Session $session,
    ) {}

    public function handle(string $code, string $ip): void
    {
        $pendingUserId = $this->session->get('mfa_pending_user_id');
        $codeHash = $this->session->get('mfa_code_hash');
        $expiresAt = $this->session->get('mfa_expires_at');

        if ($pendingUserId === null || $codeHash === null || $expiresAt === null) {
            throw ValidationException::withMessages([
                'code' => __('Aucune vérification en cours. Reconnectez-vous.'),
            ]);
        }

        $key = "mfa-verify|{$pendingUserId}|{$ip}";

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => __('Trop de tentatives. Réessayez dans :minutes minutes.', [
                    'minutes' => (int) ceil($this->limiter->availableIn($key) / 60),
                ]),
            ]);
        }

        if (CarbonImmutable::parse($expiresAt)->isPast() || ! Hash::check($code, $codeHash)) {
            $this->limiter->hit($key, self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'code' => __('Code invalide ou expiré.'),
            ]);
        }

        $this->limiter->clear($key);

        $user = User::query()->findOrFail($pendingUserId);
        $remember = (bool) $this->session->get('mfa_pending_remember', false);

        Auth::login($user, $remember);

        $this->session->forget(['mfa_pending_user_id', 'mfa_pending_remember', 'mfa_code_hash', 'mfa_expires_at']);

        $organizationId = $user->memberships()->value('organization_id');
        $this->recordAuditLog->handle(action: 'auth.login', causer: $user, organizationId: $organizationId);
    }
}
