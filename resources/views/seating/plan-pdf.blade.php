<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Plan de salle — {{ $event->title }}</title>
    <style>
        @page {
            size: landscape;
            margin: 12mm;
        }

        body {
            font-family: sans-serif;
            margin: 0;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 4mm 0;
        }

        .canvas {
            position: relative;
            width: 900px;
            height: 560px;
            border: 1px solid #ccc;
        }

        .table {
            position: absolute;
            box-sizing: border-box;
            border: 1.5px solid #111;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 10px;
            padding: 4px;
        }

        .table.round {
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <h1>{{ $event->title }} — Plan de salle</h1>

    <div class="canvas">
        @foreach ($tables as $table)
            <div
                class="table {{ $table->shape === 'round' ? 'round' : '' }}"
                style="left: {{ $table->positionX }}px; top: {{ $table->positionY }}px; width: {{ $table->width }}px; height: {{ $table->height }}px;"
            >
                {{ $table->name }}<br>({{ count($table->guests) }}/{{ $table->capacity }})
            </div>
        @endforeach
    </div>
</body>
</html>
