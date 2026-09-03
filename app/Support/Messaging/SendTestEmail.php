<?php

declare(strict_types=1);

namespace App\Support\Messaging;

use App\Domain\Contact\Models\Contact;
use App\Domain\Event\Models\Event;
use App\Domain\Messaging\Actions\SendEmail;
use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;

/**
 * Un envoi de test part toujours (SendEmail, jamais SendEmailToContact) :
 * il ne s'adresse pas réellement au contact choisi pour les données de
 * fusion — il sert à l'organisateur à vérifier le rendu, qu'il soit
 * désabonné ou non n'entre pas en jeu.
 */
final class SendTestEmail
{
    public function __construct(
        private readonly RenderEmailTemplate $renderEmailTemplate,
        private readonly SendEmail $sendEmail,
    ) {}

    public function handle(Organization $organization, EmailTemplate $template, Contact $mergeContact, ?Event $event, string $toEmail): EmailMessage
    {
        $subject = '[Test] '.$this->renderEmailTemplate->renderSubject($template, $mergeContact, $event);
        $bodyHtml = $this->renderEmailTemplate->render($template, $mergeContact, $event);

        return $this->sendEmail->handle($organization, $toEmail, $subject, $bodyHtml, null, true, null);
    }
}
