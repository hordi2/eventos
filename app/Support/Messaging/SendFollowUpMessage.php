<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Models\FollowUpChannel;
use App\Domain\Messaging\Models\FollowUpMessage;
use App\Domain\Messaging\Models\WhatsappFollowUpTemplate;
use App\Domain\Organization\Models\Organization;

/**
 * Messages que l'application envoie d'elle-même à un invité — inscription
 * acceptée ou refusée, reçu de don, fichier refusé — sur les canaux que
 * l'organisation a choisis (D1 : WhatsApp au même rang que l'e-mail).
 *
 * L'e-mail reste rendu par son appelant, qui sait quoi écrire ; ici se
 * décide seulement ce qui part, et le WhatsApp part par le modèle approuvé
 * que l'organisation a désigné pour ce cas — sans modèle, rien ne part.
 */
final class SendFollowUpMessage
{
    public function __construct(
        private readonly SendWhatsappToContact $sendWhatsappToContact,
    ) {}

    public function sendsEmail(Organization $organization): bool
    {
        return $this->channel($organization)->includesEmail();
    }

    public function sendWhatsapp(Organization $organization, ?Contact $contact, FollowUpMessage $message, ?Event $event): void
    {
        if ($contact === null || ! $this->channel($organization)->includesWhatsapp()) {
            return;
        }

        $template = WhatsappFollowUpTemplate::query()
            ->where('organization_id', $organization->id)
            ->where('purpose', $message)
            ->with('template')
            ->first();

        if ($template?->template === null) {
            return;
        }

        $this->sendWhatsappToContact->handle($organization, $contact, $template->template, $event);
    }

    private function channel(Organization $organization): FollowUpChannel
    {
        return FollowUpChannel::tryFrom((string) $organization->follow_up_channel) ?? FollowUpChannel::Email;
    }
}
