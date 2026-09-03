<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * Un billet = un JWT signé (§4.6 CLAUDE.md), jamais un ID séquentiel ou
 * devinable — la charge utile ne porte que l'ID du billet et un jti
 * (identifiant unique révocable, voir ReissueTicketQr), la signature HS256
 * garantit qu'il ne peut pas être forgé pour un autre billet.
 *
 * expiresAt est fourni par l'appelant plutôt que calculé ici : il dépend de
 * la date de l'événement, dans Domain/Event, dont ce module ne dépend
 * jamais (section 3 du CLAUDE.md).
 */
final class GenerateTicketQrToken
{
    private const ALGORITHM = 'HS256';

    public function handle(Ticket $ticket, CarbonImmutable $expiresAt): string
    {
        if ($ticket->qr_jti === null) {
            $ticket->update(['qr_jti' => (string) Str::uuid()]);
        }

        return JWT::encode([
            'tid' => $ticket->id,
            'jti' => $ticket->qr_jti,
            'iat' => CarbonImmutable::now()->timestamp,
            'exp' => $expiresAt->timestamp,
        ], (string) config('services.ticket_qr.secret'), self::ALGORITHM);
    }
}
