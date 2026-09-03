<?php

declare(strict_types=1);

namespace App\Domain\Ticketing\Actions;

use App\Domain\Ticketing\Data\TicketPdfContext;
use App\Domain\Ticketing\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * PDF « lisible et scannable depuis un écran de téléphone en basse
 * luminosité » (T-055) : QR en PNG haute résolution, noir pur sur fond
 * blanc, sans logo ni décor qui réduirait la marge de correction d'erreur.
 */
final class GenerateTicketPdf
{
    public function handle(Ticket $ticket, string $qrToken, TicketPdfContext $context): string
    {
        $qrDataUri = (new Builder(
            writer: new PngWriter,
            data: $qrToken,
            size: 320,
            margin: 12,
        ))->build()->getDataUri();

        return Pdf::loadView('tickets.pdf', [
            'ticket' => $ticket,
            'qrDataUri' => $qrDataUri,
            'context' => $context,
        ])->output();
    }
}
