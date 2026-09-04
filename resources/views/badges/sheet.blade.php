<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Badges — planche Avery</title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: sans-serif;
            margin: 0;
        }

        .sheet {
            page-break-after: always;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        table.grid {
            width: 100%;
            border-collapse: collapse;
        }

        table.grid td {
            width: 50%;
            padding: 0;
        }

        /* Repères de découpe : chaque carte garde sa propre bordure
           pointillée, coupée à mi-chemin entre deux badges voisins. */
        .badge-card {
            box-sizing: border-box;
            width: 95mm;
            height: 65mm;
            padding: 6mm;
            margin: 2.5mm;
            border-top: 4mm solid;
            outline: 0.5pt dashed #999999;
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

        .badge-table {
            font-size: 13px;
            color: #333333;
            margin-bottom: 4mm;
        }

        .badge-qr {
            width: 20mm;
            height: 20mm;
        }
    </style>
</head>
<body>
    @foreach ($pages as $page)
        <div class="sheet">
            <table class="grid">
                @foreach (array_chunk($page, 2) as $row)
                    <tr>
                        @foreach ($row as $context)
                            <td>@include('badges._card', ['context' => $context])</td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
