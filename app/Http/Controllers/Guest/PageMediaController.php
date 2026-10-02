<?php

declare(strict_types=1);

namespace App\Http\Controllers\Guest;

use App\Domain\Event\Models\Event;
use App\Domain\Page\Models\PageMedia;
use App\Http\Controllers\Controller;
use App\Support\Antivirus\FileScanStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mot d'accueil déposé dans Itaza, servi à l'invité (D1). Le fichier est
 * rangé hors du dossier public : il passe par ici, et seulement s'il est
 * sain — un média en attente d'analyse ou refusé reste introuvable.
 */
final class PageMediaController extends Controller
{
    public function __invoke(Request $request, string $organization, string $event, string $mediaToken): StreamedResponse
    {
        $eventModel = $this->event($request);

        $media = PageMedia::query()
            ->where('event_id', $eventModel->id)
            ->where('token', $mediaToken)
            ->where('scan_status', FileScanStatus::Clean)
            ->first();

        abort_if($media === null, 404);

        return Storage::disk($media->disk)->response($media->path, $media->original_name, [
            'Content-Type' => $media->mime_type,
            // Le fichier ne change jamais : son jeton est unique.
            'Cache-Control' => 'public, max-age=86400',
        ], 'inline');
    }

    /**
     * L'événement posé par resolve-guest-event.
     */
    private function event(Request $request): Event
    {
        $event = $request->attributes->get('guestEvent');
        abort_unless($event instanceof Event, 404);

        return $event;
    }
}
