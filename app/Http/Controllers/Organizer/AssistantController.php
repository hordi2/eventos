<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Assistant\DraftEventRequest;
use App\Http\Requests\Organizer\Assistant\WriteCopyRequest;
use App\Support\Assistant\AnthropicAssistant;
use App\Support\Assistant\DraftEventFromSentence;
use App\Support\Assistant\WriteMessageCopy;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

/**
 * Assistant de création et de rédaction (D4).
 *
 * Ne lui part que ce que l'organisateur écrit : sa phrase, son brief, le
 * titre de son événement. Jamais ses invités ni leurs réponses — c'est la
 * règle posée par le propriétaire du produit, et elle se tient ici.
 */
final class AssistantController extends Controller
{
    public function __construct(
        private readonly AnthropicAssistant $assistant,
    ) {}

    /**
     * Un brouillon d'événement à partir d'une phrase. Rien n'est enregistré :
     * l'organisateur reçoit une proposition, qu'il corrige puis valide dans
     * le formulaire de création.
     */
    public function draftEvent(DraftEventRequest $request, DraftEventFromSentence $draftEventFromSentence): JsonResponse
    {
        $organization = $this->currentOrganization();
        Gate::authorize('createEvents', $organization);

        if (! $this->assistant->isConfigured()) {
            return response()->json(['message' => "L'assistant n'est pas configuré sur cette installation."], 503);
        }

        try {
            $draft = $draftEventFromSentence->handle(
                $request->string('sentence')->toString(),
                $request->string('timezone')->toString() ?: 'Africa/Kinshasa',
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        return response()->json($draft);
    }

    /**
     * Un e-mail ou un message WhatsApp rédigé au ton demandé.
     */
    public function writeCopy(WriteCopyRequest $request, WriteMessageCopy $writeMessageCopy): JsonResponse
    {
        $organization = $this->currentOrganization();
        Gate::authorize('sendCommunications', $organization);

        if (! $this->assistant->isConfigured()) {
            return response()->json(['message' => "L'assistant n'est pas configuré sur cette installation."], 503);
        }

        $eventId = $request->integer('event_id');
        $eventTitle = $eventId > 0 ? Event::query()->find($eventId)?->title : null;

        try {
            if ($request->string('channel')->toString() === 'whatsapp') {
                return response()->json([
                    'body' => $writeMessageCopy->whatsapp($request->string('brief')->toString(), $request->string('tone')->toString(), $eventTitle),
                ]);
            }

            return response()->json($writeMessageCopy->email(
                $request->string('brief')->toString(),
                $request->string('tone')->toString(),
                $eventTitle,
            ));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
