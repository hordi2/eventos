<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Badge — {{ $context->guestName }}</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: sans-serif;
            margin: 0;
        }

        .badge-card {
            box-sizing: border-box;
            width: 95mm;
            height: 65mm;
            padding: 6mm;
            border-top: 4mm solid;
            text-align: center;
        }

        .badge-logo {
            max-height: 12mm;
            max-width: 40mm;
            margin-bottom: 3mm;
        }

        .badge-organization {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #555555;
        }

        .badge-event {
            font-size: 10px;
            color: #555555;
            margin-bottom: 4mm;
        }

        .badge-name {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4mm;
        }

        .badge-qr {
            width: 20mm;
            height: 20mm;
        }
    </style>
</head>
<body>
    @include('badges._card', ['context' => $context])
</body>
</html>
