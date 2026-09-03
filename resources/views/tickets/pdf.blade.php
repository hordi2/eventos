<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Billet — {{ $context->eventTitle }}</title>
    <style>
        body {
            font-family: sans-serif;
            color: #111111;
            margin: 0;
            padding: 32px;
        }

        .organization {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555555;
            margin-bottom: 4px;
        }

        h1 {
            font-size: 24px;
            margin: 0 0 24px 0;
        }

        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 32px;
        }

        table.details td {
            padding: 6px 0;
            font-size: 14px;
            vertical-align: top;
        }

        table.details td.label {
            width: 140px;
            color: #555555;
        }

        .qr-wrapper {
            text-align: center;
            padding: 24px;
            border: 2px solid #111111;
        }

        .qr-wrapper img {
            width: 260px;
            height: 260px;
        }

        .qr-caption {
            margin-top: 12px;
            font-size: 12px;
            color: #555555;
        }

        .ticket-id {
            margin-top: 8px;
            font-size: 11px;
            color: #888888;
        }
    </style>
</head>
<body>
    <div class="organization">{{ $context->organizationName }}</div>
    <h1>{{ $context->eventTitle }}</h1>

    <table class="details">
        <tr>
            <td class="label">Date</td>
            <td>{{ $context->eventStartAt->timezone($context->eventTimezone)->translatedFormat('l j F Y à H:i') }}</td>
        </tr>
        @if ($context->venueName !== null)
        <tr>
            <td class="label">Lieu</td>
            <td>{{ $context->venueName }}</td>
        </tr>
        @endif
        <tr>
            <td class="label">Type de billet</td>
            <td>{{ $context->ticketTypeName }}</td>
        </tr>
        <tr>
            <td class="label">Titulaire</td>
            <td>{{ $context->buyerName }}</td>
        </tr>
    </table>

    <div class="qr-wrapper">
        <img src="{{ $qrDataUri }}" alt="QR code du billet">
        <div class="qr-caption">Présentez ce QR code à l'entrée — un billet, une personne.</div>
    </div>

    <div class="ticket-id">Billet #{{ $ticket->id }}</div>
</body>
</html>
