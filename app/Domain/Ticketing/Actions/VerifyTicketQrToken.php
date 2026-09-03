<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidQrTokenException;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use UnexpectedValueException;

/**
 * « Le scan vérifie signature + expiration + statut + non-réutilisation »
 * (§4.6 CLAUDE.md) — les quatre facteurs, dans cet ordre : une signature
 * invalide ou un jeton expiré n'a même pas de billet à charger ; un jti qui
 * ne correspond plus au jti courant du billet signifie qu'il a été réémis
 * (ReissueTicketQr) ; un billet annulé (remboursement, T-051) reste signé
 * valide mais n'admet plus l'entrée.
 *
 * Sans global scope pour charger le billet : un scan n'a pas nécessairement
 * de contexte d'organisation déjà résolu, c'est justement ce que le jeton
 * permet de déterminer — même raisonnement que les webhooks de paiement.
 */
final class VerifyTicketQrToken
{
    private const ALGORITHM = 'HS256';

    public function handle(string $token): Ticket
    {
        try {
            $payload = JWT::decode($token, new Key((string) config('services.ticket_qr.secret'), self::ALGORITHM));
        } catch (SignatureInvalidException) {
            throw InvalidQrTokenException::invalidSignature();
        } catch (ExpiredException) {
            throw InvalidQrTokenException::expired();
        } catch (UnexpectedValueException) {
            throw InvalidQrTokenException::malformed();
        }

        $ticket = Ticket::query()->withoutGlobalScopes()->find($payload->tid ?? null);

        if ($ticket === null) {
            throw InvalidQrTokenException::unknown();
        }

        if ($ticket->qr_jti === null || $ticket->qr_jti !== ($payload->jti ?? null)) {
            throw InvalidQrTokenException::revoked();
        }

        if ($ticket->status !== TicketStatus::Valid) {
            throw InvalidQrTokenException::cancelled();
        }

        return $ticket;
    }
}
