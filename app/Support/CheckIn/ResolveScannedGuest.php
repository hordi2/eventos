<?php

declare(strict_types=1);

namespace App\Support\CheckIn;

use App\Domain\Form\Actions\VerifyAttendeeQrToken;
use App\Domain\Form\InvalidAttendeeQrTokenException;
use App\Domain\Ticketing\Actions\VerifyTicketQrToken;
use App\Domain\Ticketing\InvalidQrTokenException;
use Firebase\JWT\JWT;
use Throwable;

/**
 * Un QR scanné à l'entrée est soit un billet payé (Domain/Ticketing, « tid »),
 * soit une personne inscrite par le formulaire (Domain/Form, « aid »). Vit
 * dans Support car il traverse les deux modules (section 3 du CLAUDE.md).
 * La charge utile n'est lue ici que pour choisir le vérificateur : c'est lui
 * qui contrôle ensuite signature, expiration, révocation et statut.
 */
final class ResolveScannedGuest
{
    public function __construct(
        private readonly VerifyTicketQrToken $verifyTicketQrToken,
        private readonly VerifyAttendeeQrToken $verifyAttendeeQrToken,
    ) {}

    /**
     * @return array{type: 'attendee'|'ticket', id: int}
     *
     * @throws InvalidQrTokenException
     * @throws InvalidAttendeeQrTokenException
     */
    public function handle(string $token): array
    {
        if ($this->isAttendeeToken($token)) {
            return ['type' => 'attendee', 'id' => $this->verifyAttendeeQrToken->handle($token)->id];
        }

        return ['type' => 'ticket', 'id' => $this->verifyTicketQrToken->handle($token)->id];
    }

    private function isAttendeeToken(string $token): bool
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return false;
        }

        try {
            $payload = json_decode(JWT::urlsafeB64Decode($segments[1]), true);
        } catch (Throwable) {
            return false;
        }

        return is_array($payload) && array_key_exists('aid', $payload);
    }
}
