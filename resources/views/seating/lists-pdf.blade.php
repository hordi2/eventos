<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Listes par table — {{ $event->title }}</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 32px;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 24px 0;
        }

        .table-block {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .table-name {
            font-size: 14px;
            font-weight: bold;
            border-bottom: 1px solid #111;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        ul {
            margin: 0;
            padding-left: 18px;
        }

        li {
            font-size: 12px;
            padding: 2px 0;
        }

        .empty {
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>{{ $event->title }} — Listes par table</h1>

    @foreach ($tables as $table)
        <div class="table-block">
            <div class="table-name">{{ $table->name }} ({{ count($table->guests) }}/{{ $table->capacity }})</div>

            @if (count($table->guests) > 0)
                <ul>
                    @foreach ($table->guests as $guest)
                        <li>{{ $guest->name }}</li>
                    @endforeach
                </ul>
            @else
                <p class="empty">Aucun invité affecté.</p>
            @endif
        </div>
    @endforeach
</body>
</html>
