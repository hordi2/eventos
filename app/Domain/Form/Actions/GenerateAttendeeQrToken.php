<?php

declare(strict_types=1);

namespace App\Domain\Form\Actions;

use App\Domain\Form\Models\Attendee;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * QR d'une personne inscrite par le formulaire (T-032) : JWT signé, jamais
 * un identifiant devinable (§4.6 du CLAUDE.md), révocable en effaçant
 * qr_jti. Même secret que les billets, mais la revendication « aid » (et non
 * « tid ») empêche de prendre l'un pour l'autre.
 */
final class GenerateAttendeeQrToken
{
    private const ALGORITHM = 'HS256';

    public function handle(Attendee $attendee, CarbonImmutable $expiresAt): string
    {
        if ($attendee->qr_jti === null) {
            $attendee->update(['qr_jti' => (string) Str::uuid()]);
        }

        return JWT::encode([
            'aid' => $attendee->id,
            'jti' => $attendee->qr_jti,
            'iat' => CarbonImmutable::now()->timestamp,
            'exp' => $expiresAt->timestamp,
        ], (string) config('services.ticket_qr.secret'), self::ALGORITHM);
    }
}
