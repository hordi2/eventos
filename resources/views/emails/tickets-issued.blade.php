{{-- Corps de l'e-mail de billets (TicketsIssuedMail), glissé dans emails.generic. --}}
<h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Vos billets sont prêts</h1>
<p style="margin:0 0 24px; color:#6d655c;">
    Bonjour {{ $context['buyerName'] }},<br>
    {{ $context['organization'] }} vous confirme votre paiement pour {{ $context['event'] }}.
    {{ $context['ticketCount'] > 1 ? "Vos {$context['ticketCount']} billets sont" : 'Votre billet est' }} joint à cet e-mail, au format PDF.
</p>

<p style="margin:0; font-size:13px; color:#6d655c;">Présentez le QR code de chaque billet à l'entrée. Conservez cet e-mail : vous pourrez le retrouver si besoin.</p>
