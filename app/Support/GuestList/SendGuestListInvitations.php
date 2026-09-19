<?php

declare(strict_types=1);

namespace App\Support\GuestList;

use App\Domain\Contact\Models\EventInvitee;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Messaging\Models\MessageChannel;
use App\Domain\Messaging\Models\WhatsappTemplate;
use App\Domain\Organization\Models\Organization;
use App\Support\Messaging\GenerateEventIcs;
use App\Support\Messaging\RenderEmailTemplate;
use App\Support\Messaging\SendEmailToContact;
use App\Support\Messaging\SendWhatsappToContact;
use Carbon\CarbonImmutable;

/**
 * Envoie l'invitation aux destinataires retenus par PlanInvitationSend. Le
 * modèle choisi est rendu pour chacun : {{rsvp_link}} y devient son lien
 * personnel. Par e-mail, l'invitation porte le fichier d'agenda, comme
 * l'automatisation « Invitation », et l'e-mail en copie de l'invité. Chaque
 * envoi parti est noté sur la liste (date et canal).
 */
final class SendGuestListInvitations
{
    public function __construct(
        private readonly RenderEmailTemplate $renderEmailTemplate,
        private readonly SendEmailToContact $sendEmailToContact,
        private readonly SendWhatsappToContact $sendWhatsappToContact,
        private readonly GenerateEventIcs $generateEventIcs,
    ) {}

    /**
     * @param  list<int>  $inviteeIds
     * @return int messages partis
     */
    public function handle(Event $event, MessageChannel $channel, int $templateId, array $inviteeIds): int
    {
        $organization = Organization::query()->findOrFail($event->organization_id);
        $emailTemplate = $channel === MessageChannel::Email ? EmailTemplate::query()->findOrFail($templateId) : null;
        $whatsappTemplate = $channel === MessageChannel::Whatsapp ? WhatsappTemplate::query()->findOrFail($templateId) : null;
        $ics = $emailTemplate !== null ? $this->generateEventIcs->handle($event) : null;
        $sent = 0;

        $invitees = EventInvitee::query()->with('contact')->whereHas('contact')->where('event_id', $event->id)->whereIn('id', $inviteeIds)->get();

        foreach ($invitees as $invitee) {
            $message = $emailTemplate !== null
                ? $this->sendEmailToContact->handle(
                    $organization,
                    $invitee->contact,
                    $this->renderEmailTemplate->renderSubject($emailTemplate, $invitee->contact, $event),
                    $this->renderEmailTemplate->render($emailTemplate, $invitee->contact, $event),
                    false,
                    $ics,
                    $invitee->cc_email,
                )
                : $this->sendWhatsappToContact->handle($organization, $invitee->contact, $whatsappTemplate, $event);

            if ($message !== null) {
                $invitee->update(['last_invited_at' => CarbonImmutable::now(), 'last_invited_via' => $channel->value]);
                $sent++;
            }
        }

        return $sent;
    }
}
