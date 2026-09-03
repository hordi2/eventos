<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\InvalidQrTokenException;
use App\Domain\Ticketing\Models\Ticket;
use App\Domain\Ticketing\Models\TicketStatus;
use Illuminate\Support\Str;

/**
 * Réémission en cas de perte (§4.6 CLAUDE.md) : un nouveau jti invalide
 * immédiatement tout JWT émis pour l'ancien (VerifyTicketQrToken le rejette
 * comme révoqué dès qu'il ne correspond plus au jti courant du billet) —
 * pas besoin de liste de révocation séparée.
 */
final class ReissueTicketQr
{
    public function handle(Ticket $ticket): Ticket
    {
        if ($ticket->status !== TicketStatus::Valid) {
            throw InvalidQrTokenException::cancelled();
        }

        $ticket->update(['qr_jti' => (string) Str::uuid()]);

        return $ticket->fresh();
    }
}
