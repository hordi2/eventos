<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Registre des traitements</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 32px;
            color: #1b1611;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px 0;
        }

        .subtitle {
            font-size: 12px;
            color: #6d655c;
            margin: 0 0 24px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #e3e3e0;
            padding: 8px;
            font-size: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f4f4f2;
        }
    </style>
</head>
<body>
    <h1>Registre des traitements</h1>
    <p class="subtitle">Document généré le {{ now()->translatedFormat('d F Y') }} — Règlement (UE) 2016/679 (RGPD), article 30.</p>

    <table>
        <thead>
            <tr>
                <th>Finalité</th>
                <th>Données traitées</th>
                <th>Base légale</th>
                <th>Destinataires</th>
                <th>Durée de conservation</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activities as $activity)
                <tr>
                    <td>{{ $activity['finalite'] }}</td>
                    <td>{{ $activity['donnees'] }}</td>
                    <td>{{ $activity['base_legale'] }}</td>
                    <td>{{ $activity['destinataires'] }}</td>
                    <td>{{ $activity['conservation'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
