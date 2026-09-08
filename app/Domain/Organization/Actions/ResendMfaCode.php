<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Mail\MfaCodeMail;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

final class ResendMfaCode
{
    private const MFA_CODE_TTL_MINUTES = 10;

    public function __construct(
        private readonly Session $session,
    ) {}

    public function handle(): void
    {
        $pendingUserId = $this->session->get('mfa_pending_user_id');

        if ($pendingUserId === null) {
            throw ValidationException::withMessages([
                'code' => __('Aucune vérification en cours. Reconnectez-vous.'),
            ]);
        }

        $user = User::query()->findOrFail($pendingUserId);
        $code = (string) random_int(100000, 999999);

        $this->session->put('mfa_code_hash', Hash::make($code));
        $this->session->put('mfa_expires_at', now()->addMinutes(self::MFA_CODE_TTL_MINUTES)->toIso8601String());

        Mail::to($user->email)->queue(new MfaCodeMail($code, self::MFA_CODE_TTL_MINUTES));
    }
}
