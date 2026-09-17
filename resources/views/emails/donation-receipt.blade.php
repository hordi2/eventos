{{-- Corps du reçu de don (DonationReceiptMail), glissé dans emails.generic. --}}
@php
    $rows = array_filter([
        'Reçu n°' => $receipt['number'],
        'Montant' => $receipt['amount'],
        'Date du paiement' => $receipt['paidAt'],
        'Moyen de paiement' => $receipt['method'],
        'Cause soutenue' => $receipt['cause'],
        'Donateur' => $receipt['donorName'],
        'Entreprise' => $receipt['donorCompany'],
        'Adresse' => $receipt['donorAddress'],
    ], fn (?string $value): bool => $value !== null && $value !== '');
@endphp

<h1 style="margin:0 0 8px; font-size:22px; line-height:1.3;">Merci pour votre don</h1>
<p style="margin:0 0 24px; color:#6d655c;">{{ $receipt['organization'] }} a bien reçu votre don pour {{ $receipt['event'] }}.</p>

<table role="presentation" style="width:100%; border-collapse:collapse; font-size:14px;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="width:40%; padding:10px 12px 10px 0; border-bottom:1px solid #e3e3e0; color:#6d655c; vertical-align:top;">{{ $label }}</td>
            <td style="padding:10px 0; border-bottom:1px solid #e3e3e0; font-weight:600;">{{ $value }}</td>
        </tr>
    @endforeach
</table>

@if ($receipt['anonymous'])
    <p style="margin:24px 0 0; font-size:14px; color:#6d655c;">Vous avez demandé que votre don reste anonyme.</p>
@endif

<p style="margin:24px 0 0; font-size:13px; color:#6d655c;">Conservez cet e-mail : il atteste du paiement de votre don.</p>
