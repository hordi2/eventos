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

        {{-- Les lettres de la page web, embarquées : le faire-part se lit
             dans la même police que le site, hors ligne compris. --}}
        {!! $invitation->fontFaces !!}

        body {
            margin: 0;
            padding: 0;
            color: #1b1611;
            font-family: {!! $invitation->bodyFamily !!};
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
        .script { font-family: {!! $invitation->scriptFamily !!}; font-size: 26px; margin: 0 0 4mm 0; }

        .title { font-family: {!! $invitation->headingFamily !!}; font-weight: normal; font-size: 54px; line-height: 1.05; margin: 0; }
        .subtitle { font-family: {!! $invitation->headingFamily !!}; font-style: italic; font-size: 16px; margin: 6mm 0 0 0; }

        .monogram {
            position: absolute;
            top: 52mm;
            left: 0;
            width: 210mm;
            text-align: center;
            font-family: {!! $invitation->headingFamily !!};
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

        .date-rule td { font-family: {!! $invitation->headingFamily !!}; font-size: 32px; text-align: center; }
        .date-rule td.month { font-family: {!! $invitation->bodyFamily !!}; font-size: 10px; letter-spacing: 4px; }

        .cover-meta { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; margin: 6mm 0 0 0; }
        .guest { margin: 12mm 0 0 0; font-family: {!! $invitation->headingFamily !!}; font-style: italic; font-size: 15px; }
        .logo { height: 16mm; margin-bottom: 10mm; }

        h2 {
            font-family: {!! $invitation->headingFamily !!};
            font-style: italic;
            font-size: 24px;
            font-weight: normal;
            margin: 0;
        }

        .lead { font-size: 13px; line-height: 1.7; margin: 0 auto; width: 130mm; }
        .muted { color: #6d655c; }

        /* La date, en grand, entre deux filets — le bloc de la page web. */
        .weekday { font-size: 8px; letter-spacing: 4px; text-transform: uppercase; color: #6d655c; margin: 10mm 0 0 0; }
        .date-block { margin: 5mm auto 0 auto; width: 120mm; border-top: 1px solid #e3e3e0; border-bottom: 1px solid #e3e3e0; }
        .date-block td { font-family: {!! $invitation->headingFamily !!}; font-size: 30px; text-align: center; padding: 6mm 0; }
        .hour-line { font-size: 10px; letter-spacing: 4px; text-transform: uppercase; margin: 5mm 0 0 0; }
        .place-label { font-size: 8px; letter-spacing: 4px; text-transform: uppercase; color: #6d655c; margin: 0; }
        .place-name { font-family: {!! $invitation->headingFamily !!}; font-size: 18px; margin: 3mm 0 0 0; }

        /* Frise du programme : un filet à gauche, les moments à sa droite —
           la même composition que la page web, sur téléphone comme sur
           ordinateur. */
        .programme { margin: 0 auto; width: 150mm; }
        .programme td { vertical-align: top; }
        .programme td.left { width: 72mm; text-align: right; padding: 0 7mm 10mm 0; }
        .programme td.right { width: 72mm; text-align: left; padding: 0 0 10mm 7mm; }
        .programme td.rail { width: 1mm; padding: 0; border-left: 1px solid #e3e3e0; }
        .programme .dot { width: 2mm; height: 2mm; margin: 2mm 0 0 -1mm; border-radius: 1mm; background-color: #1b1611; }
        .on-dark .programme td.rail { border-color: rgba(255, 255, 255, 0.35); }
        .on-dark .programme .dot { background-color: #ffffff; }
        /* L'illustration se pose au-dessus de l'heure, comme sur la page web. */
        /* L'illustration suit le côté de son moment : la case l'aligne. */
        .programme .icon { margin-bottom: 2mm; }
        .programme .hour { font-family: {!! $invitation->headingFamily !!}; font-size: 20px; margin: 0; }
        .programme .name { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; margin: 2mm 0 0 0; }
        .programme .note { font-size: 11px; color: #6d655c; margin: 2mm 0 0 0; }

        .faq { margin: 0 auto; width: 130mm; }
        .faq td { padding: 5mm 0; border-bottom: 1px solid #e3e3e0; text-align: left; vertical-align: top; }
        .faq .name { font-size: 9px; letter-spacing: 3px; text-transform: uppercase; margin: 0; }
        .faq .note { font-size: 11px; color: #6d655c; margin: 2mm 0 0 0; }

        .cards { margin: 0 auto; width: 150mm; border-collapse: separate; border-spacing: 4mm; }
        .cards td { width: 50%; padding: 6mm 4mm; border: 1px solid #e3e3e0; text-align: center; vertical-align: top; }
        .cards .label { font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; margin: 0; }
        .cards .value { font-family: {!! $invitation->headingFamily !!}; font-size: 18px; margin: 3mm 0 0 0; }

        .calendar { margin: 6mm auto 0 auto; width: 110mm; }
        .calendar th { font-size: 8px; font-weight: normal; letter-spacing: 2px; text-transform: uppercase; color: #6d655c; padding-bottom: 2mm; }
        .calendar td { font-size: 11px; text-align: center; padding: 1.5mm 0; color: #6d655c; }
        /* Le jour de l'événement, posé en noir comme sur un faire-part. Le
           fond est porté par la case du tableau : sur un bloc ou un élément
           en ligne, dompdf rejette le chiffre sous le fond et il disparaît. */
        .calendar td.today {
            background-color: #1b1611;
            color: #ffffff;
            padding: 2mm 0;
        }

        .big-date { margin: 10mm auto 0 auto; }
        .big-date td { font-family: {!! $invitation->headingFamily !!}; font-size: 26px; text-align: center; vertical-align: middle; padding: 0 3mm; }
        .big-date td.day { background-color: #1b1611; color: #ffffff; font-size: 40px; padding: 2mm 5mm; }

        .gallery { margin: 0 auto; width: 150mm; border-collapse: separate; border-spacing: 3mm; }
        .gallery td { width: 50%; text-align: center; }
        .gallery img { width: 70mm; height: 52mm; }
        .gallery .caption { font-size: 9px; color: #6d655c; margin: 2mm 0 0 0; }

        .qr { margin: 8mm auto 0 auto; width: 70mm; }
        .note { font-size: 12px; line-height: 1.7; color: #6d655c; margin: 8mm auto 0 auto; width: 110mm; }
        .link { font-size: 11px; margin: 6mm auto 0 auto; width: 150mm; word-wrap: break-word; }
        .answers { margin: 8mm auto 0 auto; width: 110mm; }
        .answers td { padding: 3mm 0; }
        .answers .choice { border: 1px solid #1b1611; border-radius: 20px; padding: 3mm 6mm; font-size: 11px; text-align: center; }
        .answers .choice.first { background-color: #1b1611; color: #ffffff; }
        .footer { position: absolute; bottom: 20mm; left: 22mm; width: 166mm; text-align: center; font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }

        /* Filet gravé en cadre et numéro de feuillet : la page web porte les
           mêmes, et le faire-part la suit. */
        .frame {
            position: absolute;
            top: 10mm;
            left: 10mm;
            width: 186mm;
            height: 273mm;
            border: 1px solid #e3e3e0;
        }

        .folio { position: absolute; bottom: 14mm; left: 22mm; width: 166mm; text-align: center; font-size: 7px; letter-spacing: 4px; color: #6d655c; }
        .on-dark .folio { color: rgba(255, 255, 255, 0.6); }

        /* Le petit trait sous les titres, comme sur la page web. */
        .ornament { width: 22mm; margin: 4mm auto 9mm auto; border-top: 1px solid #c9c4bd; }
        .on-dark .ornament { border-color: rgba(255, 255, 255, 0.4); }

        /* Le mot posé sur une photo pleine page. Le voile sombre remplace le
           dégradé de la page web — dompdf n'en dessine pas — et garantit que
           le texte blanc se lise, même sur une photo claire. */
        .photo-veil { position: absolute; bottom: 0; left: 0; width: 210mm; height: 70mm; background-color: rgba(20, 16, 12, 0.45); }
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
@php($folioTotal = count($invitation->blocks) + 2)
<div class="page">
    <div class="frame"></div>
    <div class="inner">
        @if ($invitation->logoImage)
            <img src="{{ $invitation->logoImage }}" alt="" class="logo">
        @endif

        <h2>{{ __("L'essentiel") }}</h2>
        <div class="ornament"></div>

        @if ($invitation->description)
            <p class="lead">{{ $invitation->description }}</p>
        @endif

        <p class="weekday">{{ $invitation->weekday }}</p>

        <table class="date-block">
            <tr>
                <td>{{ $invitation->day }} {{ $invitation->month }} {{ $invitation->year }}</td>
            </tr>
        </table>

        <p class="hour-line">{{ $invitation->time }}</p>

        @if ($invitation->place)
            <div class="ornament"></div>

            <p class="place-label">{{ __('Lieu') }}</p>
            <p class="place-name">{{ $invitation->place }}</p>
            @if ($invitation->address)
                <p class="note" style="margin: 2mm 0 0 0">{{ $invitation->address }}</p>
            @endif
        @endif
    </div>

    <p class="folio">02 / {{ str_pad((string) $folioTotal, 2, '0', STR_PAD_LEFT) }}</p>
</div>

{{-- 3. Un feuillet par bloc, dans l'ordre de la page web — y compris les
       deux derniers, le code d'entrée et la réponse, que PageBlocks ajoute
       quand l'organisateur ne les a pas composés lui-même. --}}
@php($folioTotal = count($invitation->blocks) + 2)
@foreach ($invitation->blocks as $index => $block)
    @include('guest.page._pdf-block', [
        'block' => $block,
        'invitation' => $invitation,
        'folio' => $index + 3,
        'folioTotal' => $folioTotal,
    ])
@endforeach

</body>
</html>
