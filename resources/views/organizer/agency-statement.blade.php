<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Relevé d\'activité') }} — {{ $client->name }}</title>
    {{-- dompdf ne connaît ni flex ni grid : la mise en page tient par des
         blocs et des tableaux. --}}
    <style>
        @page { margin: 22mm 20mm; }

        body { font-family: sans-serif; color: #1b1611; font-size: 12px; }

        .eyebrow { font-size: 8px; letter-spacing: 4px; text-transform: uppercase; color: #6d655c; margin: 0; }
        h1 { font-family: serif; font-size: 26px; font-weight: normal; margin: 4mm 0 0 0; }
        .period { font-size: 11px; color: #6d655c; margin: 2mm 0 0 0; }

        .rule { border-top: 1px solid #e3e3e0; margin: 10mm 0; }

        .figures { width: 100%; margin: 0; }
        .figures td { width: 33%; text-align: center; padding: 6mm 0; border: 1px solid #e3e3e0; }
        .figures .label { font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; margin: 0; }
        .figures .value { font-family: serif; font-size: 22px; margin: 3mm 0 0 0; }

        .facts { width: 100%; margin: 10mm 0 0 0; }
        .facts td { padding: 3mm 0; border-bottom: 1px solid #e3e3e0; text-align: left; }
        .facts td.label { width: 50mm; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; color: #6d655c; }

        .note { font-size: 10px; line-height: 1.7; color: #6d655c; margin: 12mm 0 0 0; }
        .footer { position: fixed; bottom: 0; left: 0; width: 100%; text-align: center; font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }
    </style>
</head>
<body>

<p class="eyebrow">{{ $agency->name }}</p>
<h1>{{ __('Relevé d\'activité') }} — {{ $client->name }}</h1>

<p class="period">
    @if ($from && $to)
        {{ __('Du :from au :to', ['from' => $from->translatedFormat('j F Y'), 'to' => $to->translatedFormat('j F Y')]) }}
    @elseif ($from)
        {{ __('Depuis le :from', ['from' => $from->translatedFormat('j F Y')]) }}
    @elseif ($to)
        {{ __("Jusqu'au :to", ['to' => $to->translatedFormat('j F Y')]) }}
    @else
        {{ __('Depuis le premier jour') }}
    @endif
</p>

<div class="rule"></div>

<table class="figures">
    <tr>
        <td>
            <p class="label">{{ __('Événements') }}</p>
            <p class="value">{{ $client->eventCount }}</p>
        </td>
        <td>
            <p class="label">{{ __('Inscrits') }}</p>
            <p class="value">{{ $client->registrationCount }}</p>
        </td>
        <td>
            <p class="label">{{ __('Recettes') }}</p>
            <p class="value">{{ $client->revenue->format() }}</p>
        </td>
    </tr>
</table>

<table class="facts">
    @if ($client->managedSince)
        <tr>
            <td class="label">{{ __('Compte confié depuis') }}</td>
            <td>{{ $client->managedSince }}</td>
        </tr>
    @endif
    @if ($client->nextEventTitle)
        <tr>
            <td class="label">{{ __('Prochain événement') }}</td>
            <td>{{ $client->nextEventTitle }} &middot; {{ $client->nextEventDate }}</td>
        </tr>
    @endif
</table>

<p class="note">
    {{ __("Ce relevé rend compte de l'activité gérée par :agency sur le compte de :client pendant la période indiquée. Les recettes sont celles encaissées par la billetterie, hors remboursements. Il accompagne la facture de l'agence ; il n'en tient pas lieu.", ['agency' => $agency->name, 'client' => $client->name]) }}
</p>

<p class="footer">{{ $agency->name }} &middot; {{ $client->name }}</p>

</body>
</html>
