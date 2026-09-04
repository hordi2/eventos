<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Http\Controllers\Controller;
use App\Support\Dashboard\DashboardStatsData;
use App\Support\Dashboard\GetEventDashboardStats;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tableau de bord événement temps réel (M8.1, T-070) : mise à jour sans
 * rechargement via SSE plutôt que WebSocket (les deux sont acceptés par le
 * critère d'acceptation) — aucune dépendance ni processus supplémentaire à
 * superviser, suffisant puisque le tableau de bord n'a besoin que de
 * recevoir, jamais d'émettre.
 */
final class EventDashboardController extends Controller
{
    /**
     * Intervalle entre deux mesures envoyées au navigateur : assez court
     * pour paraître "temps réel" à l'œil, assez long pour ne pas marteler
     * la base sur un poste resté ouvert toute la journée de l'événement.
     */
    private const PUSH_INTERVAL_SECONDS = 5;

    /**
     * Durée maximale d'une connexion avant fermeture volontaire : EventSource
     * se reconnecte automatiquement côté navigateur (quasi instantané), ce
     * qui évite de garder un worker PHP-FPM occupé trop longtemps sur une
     * page laissée ouverte — chaque connexion SSE ouverte immobilise un
     * worker pour toute sa durée, contrairement à une requête HTTP normale.
     * Volontairement courte : avec beaucoup d'organisateurs gardant le
     * tableau de bord ouvert en même temps, un pool de workers trop petit
     * pourrait s'épuiser si cette valeur était trop généreuse — au-delà
     * d'un certain volume, ce compromis justifierait de passer à un serveur
     * asynchrone (Octane) ou à un vrai serveur WebSocket (Reverb), qui ne
     * consomment pas un worker de requête par connexion ouverte.
     */
    private const MAX_STREAM_SECONDS = 30;

    public function index(int $event, GetEventDashboardStats $getEventDashboardStats): Response
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        return Inertia::render('EventDashboard/Show', [
            'event' => ['id' => $event->id, 'title' => $event->title],
            'stats' => $this->serialize($getEventDashboardStats->handle($event)),
        ]);
    }

    public function stream(int $event, GetEventDashboardStats $getEventDashboardStats): StreamedResponse
    {
        $event = $this->findEvent($event);
        Gate::authorize('viewGuests', $event->organization);

        return response()->stream(function () use ($event, $getEventDashboardStats): void {
            // Pas de verrou de session à craindre ici (SESSION_DRIVER=database,
            // pas de session native PHP) : une autre requête de l'organisateur
            // reste libre de s'exécuter pendant que ce flux SSE tourne.
            //
            // max_execution_time (30 s par défaut) s'applique au temps total du
            // script, y compris celui passé dans sleep() : sans ce set_time_limit,
            // PHP tue la connexion en erreur fatale bien avant MAX_STREAM_SECONDS
            // (trouvé en testant ce flux en conditions réelles).
            set_time_limit(self::MAX_STREAM_SECONDS + 5);

            // Toute mise en tampon active (dev server PHP compris) retarde
            // l'arrivée des événements côté navigateur, parfois jusqu'à la
            // fermeture de la connexion — trouvé en testant ce flux en
            // conditions réelles, où aucun événement n'arrivait avant la
            // coupure. ob_implicit_flush pousse chaque echo immédiatement.
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            ob_implicit_flush(true);

            $startedAt = time();

            while (! connection_aborted() && (time() - $startedAt) < self::MAX_STREAM_SECONDS) {
                $stats = $getEventDashboardStats->handle($event->fresh());

                echo 'event: stats'."\n";
                echo 'data: '.json_encode($this->serialize($stats))."\n\n";

                flush();

                sleep(self::PUSH_INTERVAL_SECONDS);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DashboardStatsData $stats): array
    {
        return [
            'confirmed_count' => $stats->confirmedCount,
            'present_count' => $stats->presentCount,
            'presence_rate' => $stats->presenceRate,
            'registration_curve' => $stats->registrationCurve,
            'arrival_curve' => $stats->arrivalCurve,
        ];
    }

    private function findEvent(int $id): Event
    {
        return Event::query()->findOrFail($id);
    }
}
