{{-- Corps de ProposalDecisionMail, glissé dans emails.generic. --}}
@if ($accepted)
    <h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre sujet est retenu</h1>
    <p style="margin:0 0 16px;">
        Bonjour {{ $proposerName }}, {{ $organizationName }} retient votre sujet « {{ $proposalTitle }} » pour {{ $eventTitle }}.
    </p>
    <p style="margin:0 0 16px;">Vous recevrez le lien de votre espace intervenant : vous y confirmerez votre créneau et y déposerez votre support.</p>
@else
    <h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Votre proposition n'a pas été retenue</h1>
    <p style="margin:0 0 16px;">
        Bonjour {{ $proposerName }}, {{ $organizationName }} n'a pas pu retenir votre sujet « {{ $proposalTitle }} » pour {{ $eventTitle }}.
    </p>
@endif

@if ($note)
    <p style="margin:0 0 16px;">Message de l'organisateur : {{ $note }}</p>
@endif

<p style="margin:0;">Merci d'avoir proposé, et à bientôt.</p>
