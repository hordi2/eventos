<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Invitation') }} — {{ $invitation->title }}</title>
    {{-- dompdf ne connaît ni flex ni grid : la mise en page tient par des
         blocs positionnés et des tableaux, et les polices se limitent à
         celles qu'il embarque — « serif » y est un Times, qui garde
         l'esprit du Bodoni de la page web. --}}
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            color: #1b1611;
            font-family: sans-serif;
        }

        .page {
            position: relative;
            width: 100%;
            height: 297mm;
            page-break-after: always;
            overflow: hidden;
        }

        .page:last-child { page-break-after: auto; }

        .cover-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
        }

        /* rgba() plutôt qu'opacity : dompdf éclaircit tout le bloc avec
           opacity, alors qu'il pose une couleur translucide correctement. */
        .cover-veil {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            background-color: rgba(20, 16, 12, 0.58);
        }

        .cover-veil.solid { background-color: #1b1611; }

        .cover-inner {
            position: absolute;
            top: 62mm;
            left: 18mm;
            width: 174mm;
            text-align: center;
            color: #ffffff;
        }

        .inner {
            position: absolute;
            top: 32mm;
            left: 22mm;
            width: 166mm;
            text-align: center;
        }

        .eyebrow {
            font-size: 9px;
            letter-spacing: 4px;
            text-transform: uppercase;
            margin: 0 0 10mm 0;
        }

        .title {
            font-family: serif;
            font-size: 54px;
            line-height: 1.05;
            margin: 0;
        }

        .subtitle {
            font-family: serif;
            font-style: italic;
            font-size: 16px;
            margin: 6mm 0 0 0;
        }

        .date-rule {
            margin: 14mm auto 0 auto;
            width: 110mm;
            border-top: 1px solid rgba(255, 255, 255, 0.45);
            border-bottom: 1px solid rgba(255, 255, 255, 0.45);
            padding: 5mm 0;
        }

        .date-rule td { font-family: serif; font-size: 32px; text-align: center; }
        .date-rule td.month { font-family: sans-serif; font-size: 10px; letter-spacing: 4px; }

        .cover-meta { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; margin: 6mm 0 0 0; }

        .guest {
            margin: 12mm 0 0 0;
            font-family: serif;
            font-style: italic;
            font-size: 15px;
        }

        .logo { height: 16mm; margin-bottom: 10mm; }

        h2 {
            font-family: serif;
            font-style: italic;
            font-size: 24px;
            font-weight: normal;
            margin: 0 0 10mm 0;
        }

        .lead { font-size: 13px; line-height: 1.7; margin: 0 auto; width: 130mm; }

        .facts { margin: 12mm auto 0 auto; width: 130mm; }
        .facts td { padding: 4mm 0; border-bottom: 1px solid #e3e3e0; font-size: 12px; text-align: left; }
        .facts td.label { width: 40mm; font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }

        .programme { margin: 0 auto; width: 130mm; }
        .programme td { padding: 6mm 0; border-bottom: 1px solid #e3e3e0; text-align: left; vertical-align: top; }
        .programme td.time { width: 34mm; font-family: serif; font-size: 20px; }
        .programme .name { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; }
        .programme .note { font-size: 12px; color: #6d655c; margin: 2mm 0 0 0; }

        .qr { margin: 8mm auto 0 auto; width: 70mm; }
        .note { font-size: 12px; line-height: 1.7; color: #6d655c; margin: 8mm auto 0 auto; width: 110mm; }
        .link { font-size: 11px; color: #1b1611; margin: 6mm auto 0 auto; width: 150mm; word-wrap: break-word; }
        .footer { position: absolute; bottom: 20mm; left: 22mm; width: 166mm; text-align: center; font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }
    </style>
</head>
<body>

{{-- 1. La couverture : la photo, le titre, la date. --}}
<div class="page">
    @if ($invitation->coverImage)
        <img src="{{ $invitation->coverImage }}" alt="" class="cover-image">
        <div class="cover-veil"></div>
    @else
        <div class="cover-veil solid"></div>
    @endif

    <div class="cover-inner">
        <p class="eyebrow">{{ $invitation->eyebrow }}</p>
        <h1 class="title">{{ $invitation->title }}</h1>
        @if ($invitation->subtitle)
            <p class="subtitle">{{ $invitation->subtitle }}</p>
        @endif

        <table class="date-rule">
            <tr>
                <td>{{ $invitation->day }}</td>
                <td class="month">{{ $invitation->month }}</td>
                <td>{{ $invitation->year }}</td>
            </tr>
        </table>

        <p class="cover-meta">
            {{ $invitation->time }}@if ($invitation->place) &middot; {{ $invitation->place }}@endif
        </p>

        @if ($invitation->guestName)
            <p class="guest">{{ __('Invitation adressée à :name', ['name' => $invitation->guestName]) }}</p>
        @endif
    </div>
</div>

{{-- 2. L'essentiel : quand, où, et le mot de l'organisateur. --}}
<div class="page">
    <div class="inner">
        @if ($invitation->logoImage)
            <img src="{{ $invitation->logoImage }}" alt="" class="logo">
        @endif

        <h2>{{ __("L'essentiel") }}</h2>

        @if ($invitation->description)
            <p class="lead">{{ $invitation->description }}</p>
        @endif

        <table class="facts">
            <tr>
                <td class="label">{{ __('Date') }}</td>
                <td>{{ $invitation->fullDate }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Heure') }}</td>
                <td>{{ $invitation->time }}</td>
            </tr>
            @if ($invitation->place)
                <tr>
                    <td class="label">{{ __('Lieu') }}</td>
                    <td>
                        {{ $invitation->place }}
                        @if ($invitation->address)<br>{{ $invitation->address }}@endif
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <p class="footer">{{ $invitation->title }} &middot; {{ $invitation->fullDate }}</p>
</div>

{{-- 3. Le programme, quand l'organisateur en a écrit un. --}}
@if ($invitation->programme !== [])
    <div class="page">
        <div class="inner">
            <h2>{{ __('Programme') }}</h2>

            <table class="programme">
                @foreach ($invitation->programme as $item)
                    <tr>
                        <td class="time">{{ $item['time'] ?? '' }}</td>
                        <td>
                            <p class="name">{{ $item['title'] }}</p>
                            @if ($item['description'])
                                <p class="note">{{ $item['description'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>

        <p class="footer">{{ $invitation->title }} &middot; {{ $invitation->fullDate }}</p>
    </div>
@endif

{{-- 4. L'entrée : le code QR à présenter, quand l'invité est inscrit. --}}
@if ($invitation->entryQr)
    <div class="page">
        <div class="inner">
            <h2>{{ __('Votre entrée') }}</h2>
            <img src="{{ $invitation->entryQr }}" alt="" class="qr">
            <p class="note">{{ $invitation->entryNote }}</p>
        </div>

        <p class="footer">{{ $invitation->title }} &middot; {{ $invitation->fullDate }}</p>
    </div>
@endif

{{-- 5. La réponse : le lien, et son QR pour ceux qui tiennent le papier. --}}
<div class="page">
    <div class="inner">
        <h2>{{ __('Confirmez votre présence') }}</h2>
        <p class="note" style="margin-top: 0">{{ __('Scannez ce code, ou ouvrez le lien ci-dessous.') }}</p>
        <img src="{{ $invitation->rsvpQr }}" alt="" class="qr">
        <p class="link">{{ $invitation->rsvpUrl }}</p>
    </div>

    <p class="footer">{{ __('Cordiale bienvenue') }}</p>
</div>

</body>
</html>
