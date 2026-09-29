{{-- Corps de RegistrationDecisionMail, glissé dans emails.generic. --}}
@if ($approved)
    <h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre inscription est confirmée</h1>
    <p style="margin:0 0 16px;">
        {{ $organizationName }} a validé votre inscription à {{ $eventTitle }}. Nous avons hâte de vous y retrouver.
    </p>

    @if ($confirmationUrl)
        <p style="margin:0;">
            <a href="{{ $confirmationUrl }}" style="display:inline-block; padding:12px 24px; border-radius:999px; background:#1b1611; color:#ffffff; text-decoration:none; font-weight:600;">Voir mon inscription et mon QR code</a>
        </p>
    @endif
@else
    <h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre demande n'a pas pu être retenue</h1>
    <p style="margin:0 0 16px;">
        {{ $organizationName }} n'a pas pu retenir votre demande d'inscription à {{ $eventTitle }}.
    </p>

    @if ($reason)
        <p style="margin:0 0 16px;">Motif indiqué : {{ $reason }}</p>
    @endif

    <p style="margin:0;">Pour toute question, contactez {{ $organizationName }}.</p>
@endif
