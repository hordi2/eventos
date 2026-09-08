<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class MfaCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $code,
        private readonly int $ttlMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion Itaza');
    }

    public function content(): Content
    {
        $message = "Votre code de connexion à usage unique : <strong>{$this->code}</strong>. ".
            "Il expire dans {$this->ttlMinutes} minutes. Ignorez cet e-mail si vous n'êtes pas à l'origine de cette tentative.";

        return new Content(
            view: 'emails.generic',
            with: [
                'bodyHtml' => '<p>'.$message.'</p>',
                'unsubscribeUrl' => null,
                'organizationLogoUrl' => null,
                'organizationPrimaryColor' => null,
            ],
        );
    }
}
