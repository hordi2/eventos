{{-- Corps de SpeakerPortalInvitationMail, glissé dans emails.generic. --}}
<h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre espace intervenant</h1>

<p style="margin:0 0 16px;">
    Bonjour {{ $speakerName }}, {{ $organizationName }} vous attend à {{ $eventTitle }} ({{ $eventSchedule }}).
</p>

<p style="margin:0 0 16px;">
    Depuis votre espace, confirmez votre créneau, déposez votre support et retrouvez les informations pratiques.
</p>

<p style="margin:0 0 16px;">
    <a href="{{ $portalUrl }}" style="display:inline-block; padding:12px 24px; border-radius:999px; background:#1b1611; color:#ffffff; text-decoration:none; font-weight:600;">Ouvrir mon espace intervenant</a>
</p>

<p style="margin:0;">Ce lien est personnel : ne le transmettez pas.</p>
