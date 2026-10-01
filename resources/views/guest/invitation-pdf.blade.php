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

        .bleed {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
        }

        /* rgba() plutôt qu'opacity : dompdf éclaircit tout le bloc avec
           opacity, alors qu'il pose une couleur translucide correctement. */
        .veil { position: absolute; top: 0; left: 0; width: 210mm; height: 297mm; }

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
            top: 30mm;
            left: 22mm;
            width: 166mm;
            text-align: center;
        }

        .on-dark { color: #ffffff; }
        .on-dark .muted { color: rgba(255, 255, 255, 0.75); }
        .on-dark .rule td { border-color: rgba(255, 255, 255, 0.35); }
        .on-dark .card { border-color: rgba(255, 255, 255, 0.35); }

        .eyebrow { font-size: 9px; letter-spacing: 4px; text-transform: uppercase; margin: 0 0 10mm 0; }
        .script { font-family: serif; font-style: italic; font-size: 26px; margin: 0 0 4mm 0; }

        .title { font-family: serif; font-size: 54px; line-height: 1.05; margin: 0; }
        .subtitle { font-family: serif; font-style: italic; font-size: 16px; margin: 6mm 0 0 0; }

        .monogram {
            position: absolute;
            top: 95mm;
            left: 0;
            width: 210mm;
            text-align: center;
            font-family: serif;
            font-size: 150px;
            color: rgba(255, 255, 255, 0.09);
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
        .guest { margin: 12mm 0 0 0; font-family: serif; font-style: italic; font-size: 15px; }
        .logo { height: 16mm; margin-bottom: 10mm; }

        h2 {
            font-family: serif;
            font-style: italic;
            font-size: 24px;
            font-weight: normal;
            margin: 0 0 10mm 0;
        }

        .lead { font-size: 13px; line-height: 1.7; margin: 0 auto; width: 130mm; }
        .muted { color: #6d655c; }

        .facts { margin: 12mm auto 0 auto; width: 130mm; }
        .facts td { padding: 4mm 0; border-bottom: 1px solid #e3e3e0; font-size: 12px; text-align: left; }
        .facts td.label { width: 40mm; font-size: 9px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }

        .programme { margin: 0 auto; width: 130mm; }
        .programme td { padding: 6mm 0; border-bottom: 1px solid #e3e3e0; text-align: left; vertical-align: top; }
        .programme td.time { width: 34mm; font-family: serif; font-size: 20px; }
        .programme .name { font-size: 10px; letter-spacing: 3px; text-transform: uppercase; margin: 0; }
        .programme .note { font-size: 12px; color: #6d655c; margin: 2mm 0 0 0; }

        .cards { margin: 0 auto; width: 150mm; border-collapse: separate; border-spacing: 4mm; }
        .cards td { width: 50%; padding: 6mm 4mm; border: 1px solid #e3e3e0; text-align: center; vertical-align: top; }
        .cards .label { font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; margin: 0; }
        .cards .value { font-family: serif; font-size: 18px; margin: 3mm 0 0 0; }

        .calendar { margin: 6mm auto 0 auto; width: 110mm; }
        .calendar th { font-size: 8px; font-weight: normal; letter-spacing: 2px; text-transform: uppercase; color: #6d655c; padding-bottom: 2mm; }
        .calendar td { font-size: 11px; text-align: center; padding: 1.5mm 0; color: #6d655c; }
        /* Le jour de l'événement, entouré de noir comme sur un faire-part. */
        .calendar .today {
            display: inline-block;
            width: 7mm;
            height: 7mm;
            line-height: 7mm;
            border-radius: 4mm;
            background-color: #1b1611;
            color: #ffffff;
        }

        .big-date { margin: 10mm auto 0 auto; }
        .big-date td { font-family: serif; font-size: 26px; text-align: center; vertical-align: middle; padding: 0 3mm; }
        .big-date td.day { background-color: #1b1611; color: #ffffff; font-size: 40px; padding: 2mm 5mm; }

        .gallery { margin: 0 auto; width: 150mm; border-collapse: separate; border-spacing: 3mm; }
        .gallery td { width: 50%; text-align: center; }
        .gallery img { width: 70mm; height: 52mm; }

        .qr { margin: 8mm auto 0 auto; width: 70mm; }
        .note { font-size: 12px; line-height: 1.7; color: #6d655c; margin: 8mm auto 0 auto; width: 110mm; }
        .link { font-size: 11px; margin: 6mm auto 0 auto; width: 150mm; word-wrap: break-word; }
        .answers { margin: 8mm auto 0 auto; width: 110mm; }
        .answers td { padding: 3mm 0; }
        .answers .choice { border: 1px solid #1b1611; border-radius: 20px; padding: 3mm 6mm; font-size: 11px; text-align: center; }
        .answers .choice.first { background-color: #1b1611; color: #ffffff; }
        .footer { position: absolute; bottom: 20mm; left: 22mm; width: 166mm; text-align: center; font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }

        .photo-caption { position: absolute; bottom: 24mm; left: 20mm; width: 170mm; text-align: center; color: #ffffff; }
    </style>
</head>
<body>

{{-- 1. La couverture : la photo, le titre, la date, le nom de l'invité. --}}
<div class="page">
    @if ($invitation->coverImage)
        <img src="{{ $invitation->coverImage }}" alt="" class="bleed">
        <div class="veil" style="background-color: rgba(20, 16, 12, {{ $invitation->coverOverlay }})"></div>
    @else
        <div class="veil" style="background-color: #1b1611"></div>
    @endif

    @if ($invitation->monogram)
        <p class="monogram">{{ $invitation->monogram }}</p>
    @endif

    <div class="cover-inner">
        <p class="eyebrow">{{ $invitation->eyebrow }}</p>

        @if ($invitation->script)
            <p class="script">{{ $invitation->script }}</p>
        @endif

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

{{-- 3. Un feuillet par bloc composé par l'organisateur, dans son ordre et
       avec son fond : le papier suit la page web. --}}
@foreach ($invitation->blocks as $block)
    @include('guest.page._pdf-block', ['block' => $block, 'invitation' => $invitation])
@endforeach

{{-- 4. L'entrée, quand l'invité est inscrit et qu'aucun bloc ne l'a déjà
       montrée. --}}
@if ($invitation->entryQr && ! collect($invitation->blocks)->contains('type', 'entry_qr'))
    <div class="page">
        <div class="inner">
            <h2>{{ __('Votre entrée') }}</h2>
            <img src="{{ $invitation->entryQr }}" alt="" class="qr">
            <p class="note">{{ $invitation->entryNote }}</p>
        </div>

        <p class="footer">{{ $invitation->title }} &middot; {{ $invitation->fullDate }}</p>
    </div>
@endif

{{-- 5. La réponse, toujours en dernier. --}}
@unless (collect($invitation->blocks)->contains('type', 'rsvp'))
    <div class="page">
        <div class="inner">
            <h2>{{ __('Confirmez votre présence') }}</h2>

            @if ($invitation->guestName)
                <p class="note" style="margin-top: 0">{{ __('Invitation adressée à :name', ['name' => $invitation->guestName]) }}</p>
            @endif

            <p class="note" style="margin-top: 2mm">{{ __('Scannez ce code, ou ouvrez le lien ci-dessous.') }}</p>
            <img src="{{ $invitation->rsvpQr }}" alt="" class="qr">
            <p class="link">{{ $invitation->rsvpUrl }}</p>
        </div>

        <p class="footer">{{ __('Cordiale bienvenue') }}</p>
    </div>
@endunless

</body>
</html>
