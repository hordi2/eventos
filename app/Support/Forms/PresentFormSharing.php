<?php

declare(strict_types=1);

namespace App\Support\Forms;

use App\Domain\Event\Models\Event;
use App\Domain\Event\Models\EventStatus;
use App\Domain\Form\Models\Form;
use App\Support\Events\EventPublicLinks;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Bloc « Partager » du constructeur, une fois le formulaire publié : son lien,
 * prêt à copier, ouvrir, envoyer par WhatsApp ou e-mail, et en QR code pour
 * une affiche.
 *
 * Un formulaire publié n'est pas en ligne tant que l'événement reste
 * « Inédit » : le lien de test (valable quelques jours) est alors donné, avec
 * de quoi publier l'événement.
 */
final class PresentFormSharing
{
    public function __construct(
        private readonly EventPublicLinks $eventPublicLinks,
    ) {}

    /**
     * @return array{available: bool, url: string|null, isPreview: bool, previewDays: int, qrCode: string|null, whatsappUrl: string|null, mailtoUrl: string|null}
     */
    public function handle(Form $form, Event $event): array
    {
        if (! $form->hasPublishedVersion()) {
            return ['available' => false, 'url' => null, 'isPreview' => false, 'previewDays' => EventPublicLinks::PREVIEW_VALIDITY_DAYS, 'qrCode' => null, 'whatsappUrl' => null, 'mailtoUrl' => null];
        }

        $isPreview = $event->status !== EventStatus::Published;
        // Le formulaire par défaut répond au lien de l'événement, les autres au leur.
        $slug = $form->is_default ? null : $form->slug;
        $url = $isPreview ? $this->eventPublicLinks->previewUrl($event, $slug) : $this->eventPublicLinks->publicUrl($event, $slug);
        $message = "Répondez à l'invitation « {$event->title} » : {$url}";

        return [
            'available' => true,
            'url' => $url,
            'isPreview' => $isPreview,
            'previewDays' => EventPublicLinks::PREVIEW_VALIDITY_DAYS,
            // Un lien de test ne va pas sur une affiche : pas de QR code tant que l'événement est « Inédit ».
            'qrCode' => $isPreview ? null : (new Builder(writer: new PngWriter, data: $url, size: 480, margin: 16))->build()->getDataUri(),
            'whatsappUrl' => 'https://wa.me/?text='.rawurlencode($message),
            'mailtoUrl' => 'mailto:?subject='.rawurlencode("Invitation : {$event->title}").'&body='.rawurlencode($message),
        ];
    }
}
