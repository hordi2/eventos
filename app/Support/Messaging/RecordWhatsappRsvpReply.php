<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\SubmitRegistration;
use App\Domain\Form\Data\AttendeeIdentity;
use App\Domain\Form\Data\FormVisibilityContext;
use App\Domain\Form\Data\RegistrationSubmissionMetadata;
use App\Domain\Form\EventFullException;
use App\Domain\Form\Models\Form;
use App\Domain\Form\OptionFullException;
use App\Domain\Form\RegistrationClosedException;
use App\Domain\Form\Support\EventForms;
use App\Support\MultiTenancy\CurrentOrganization;
use App\Support\Registration\BuildEventRegistrationContext;
use App\Support\Registration\DeclineAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bouton WhatsApp « Je viens » / « Je ne viens pas » cliqué par l'invité
 * (D1 : WhatsApp au même rang que l'e-mail). L'URL de rappel Twilio est
 * globale (un seul numéro pour toute la plateforme, réglé une fois dans la
 * console) : l'organisation et le jeton d'invitation voyagent dans la
 * charge utile du bouton lui-même (rsvp_reference, voir ResolveMergeVariables),
 * jamais dans l'URL.
 *
 * Traverse Contact (par l'invitation), Event et Form : sa place est dans
 * Support (section 3 du CLAUDE.md), comme RegistrationController dont elle
 * reprend le parcours de soumission (SubmitRegistration, BuildEventRegistrationContext).
 *
 * Seul le premier clic compte : la clé d'idempotence de SubmitRegistration
 * ("whatsapp:{organisation}:{jeton}", stable pour cette invitation plutôt
 * que liée au message Twilio) fait qu'un second clic — même sur l'autre
 * bouton — retombe sur l'inscription déjà créée, sans la modifier. Changer
 * de réponse après coup reste le rôle du lien personnel de l'invité
 * (rsvp_link), pas de ce webhook.
 */
final class RecordWhatsappRsvpReply
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly EventForms $eventForms,
        private readonly BuildEventRegistrationContext $buildEventRegistrationContext,
        private readonly SubmitRegistration $submitRegistration,
    ) {}

    public function handle(string $messageSid, string $from, string $buttonPayload): void
    {
        DB::transaction(function () use ($messageSid, $from, $buttonPayload): void {
            // Même mécanique d'idempotence que RecordWhatsappWebhookEvent
            // (règle 4.4 du CLAUDE.md), préfixée pour ne jamais entrer en
            // collision avec un rappel de statut sortant sur le même MessageSid.
            $inserted = DB::table('whatsapp_webhook_events')->insertOrIgnore([[
                'provider' => 'twilio',
                'event_id' => "inbound:{$messageSid}",
                'payload' => json_encode(['From' => $from, 'ButtonPayload' => $buttonPayload]),
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]]);

            if ($inserted === 0) {
                Log::info("Réponse WhatsApp déjà traitée, ignorée : {$messageSid}.");

                return;
            }

            $this->process($from, $buttonPayload);
        });
    }

    private function process(string $from, string $buttonPayload): void
    {
        $parsed = $this->parseButtonPayload($buttonPayload);

        if ($parsed === null) {
            // Message libre (pas un clic de bouton reconnu) : aucune réponse
            // en langage naturel n'est traitée (hors périmètre, §9 du CLAUDE.md).
            return;
        }

        [$attending, $organizationId, $token] = $parsed;

        $this->currentOrganization->set($organizationId);

        $invitee = EventInvitee::query()->where('invitation_token', $token)->with('contact')->first();

        if ($invitee === null || $invitee->contact === null) {
            Log::warning('Réponse WhatsApp pour une invitation introuvable.');

            return;
        }

        $phone = Str::after($from, 'whatsapp:');

        if ($invitee->contact->phone_e164 !== $phone) {
            // Le numéro qui répond n'est pas celui du contact invité : on ne
            // devine jamais qui répond, on ignore (ni inscription ni erreur).
            Log::warning('Réponse WhatsApp dont le numéro ne correspond pas au contact invité.');

            return;
        }

        $event = Event::query()->find($invitee->event_id);

        if ($event === null) {
            return;
        }

        $form = $this->eventForms->forLink($event->id, null);

        if ($form === null || $form->current_version_id === null) {
            return;
        }

        if (! $attending && ! DeclineAnswer::isOffered($event, $form)) {
            // « Je ne viens pas » ne fait pas partie des réponses proposées
            // par cet événement (DeclineAnswer) : rien à enregistrer.
            return;
        }

        $this->submitReply($event, $form, $invitee, $phone, $attending, $organizationId, $token);
    }

    private function submitReply(Event $event, Form $form, EventInvitee $invitee, string $phone, bool $attending, int $organizationId, string $token): void
    {
        $identity = new AttendeeIdentity(
            email: $invitee->contact->email ?? '',
            firstName: $invitee->contact->first_name,
            lastName: $invitee->contact->last_name,
            phone: $phone,
            contactId: $invitee->contact_id,
        );

        try {
            $this->submitRegistration->handle(
                context: $this->buildEventRegistrationContext->handle($event),
                formVersion: $form->currentVersion()->firstOrFail(),
                identity: $identity,
                answers: [],
                metadata: new RegistrationSubmissionMetadata(source: 'whatsapp'),
                idempotencyKey: "whatsapp:{$organizationId}:{$token}",
                visibilityContext: new FormVisibilityContext(attending: $attending),
            );
        } catch (ValidationException|OptionFullException|RegistrationClosedException|EventFullException) {
            // Une question obligatoire, un quota ou une fenêtre fermée
            // empêchent une réponse en un seul clic : l'invité reste non
            // inscrit plutôt que risquer une inscription incomplète ou
            // invalide — à lui d'utiliser son lien personnel (rsvp_link)
            // pour répondre en détail. Le webhook répond tout de même 200 à
            // Twilio : ce n'est jamais une panne du canal.
            Log::info("Réponse WhatsApp non traitée (question obligatoire, quota ou fenêtre fermée) pour l'invitation {$token}.");
        }
    }

    /**
     * @return array{0: bool, 1: int, 2: string}|null
     */
    private function parseButtonPayload(string $payload): ?array
    {
        foreach (['rsvp_yes:' => true, 'rsvp_no:' => false] as $prefix => $attending) {
            if (str_starts_with($payload, $prefix)) {
                return $this->splitReference($attending, substr($payload, strlen($prefix)));
            }
        }

        return null;
    }

    /**
     * @return array{0: bool, 1: int, 2: string}|null
     */
    private function splitReference(bool $attending, string $reference): ?array
    {
        $parts = explode(':', $reference, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || $parts[1] === '') {
            return null;
        }

        return [$attending, (int) $parts[0], $parts[1]];
    }
}
