{{-- Corps de RejectedRegistrationFileMail, glissé dans emails.generic. --}}
<h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre fichier n'a pas pu être accepté</h1>
<p style="margin:0 0 16px;">
    Le fichier « {{ $fileName }} », envoyé pour {{ $eventTitle }} en réponse à la question « {{ $question }} »,
    a été refusé par notre analyse antivirus puis supprimé. Votre inscription reste enregistrée.
</p>

@if ($editUrl)
    <p style="margin:0 0 24px;">Vous pouvez en envoyer un autre depuis votre inscription.</p>
    <p style="margin:0;">
        <a href="{{ $editUrl }}" style="display:inline-block; padding:12px 24px; border-radius:999px; background:#1b1611; color:#ffffff; text-decoration:none; font-weight:600;">Envoyer un autre fichier</a>
    </p>
@else
    <p style="margin:0;">Pour transmettre un autre fichier, contactez {{ $organizationName }}.</p>
@endif
