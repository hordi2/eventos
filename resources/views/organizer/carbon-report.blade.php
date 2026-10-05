<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ __('Empreinte carbone') }} — {{ $event->title }}</title>
    {{-- dompdf ne connaît ni flex ni grid : blocs et tableaux. --}}
    <style>
        @page { margin: 22mm 20mm; }

        /* « CO2e » plutôt que « CO₂e » : la police embarquée par dompdf n'a
           pas l'indice, qui sortirait en point d'interrogation. */
        body { font-family: sans-serif; color: #1b1611; font-size: 12px; }

        .eyebrow { font-size: 8px; letter-spacing: 4px; text-transform: uppercase; color: #6d655c; margin: 0; }
        h1 { font-family: serif; font-size: 26px; font-weight: normal; margin: 4mm 0 0 0; }
        h2 { font-family: serif; font-size: 16px; font-weight: normal; margin: 12mm 0 4mm 0; }
        .period { font-size: 11px; color: #6d655c; margin: 2mm 0 0 0; }

        .rule { border-top: 1px solid #e3e3e0; margin: 10mm 0; }

        .figures { width: 100%; }
        .figures td { width: 33%; text-align: center; padding: 6mm 0; border: 1px solid #e3e3e0; }
        .figures .label { font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; margin: 0; }
        .figures .value { font-family: serif; font-size: 22px; margin: 3mm 0 0 0; }

        .table { width: 100%; margin: 0; }
        .table td, .table th { padding: 3mm 0; border-bottom: 1px solid #e3e3e0; text-align: left; font-weight: normal; }
        .table th { font-size: 8px; letter-spacing: 2px; text-transform: uppercase; color: #6d655c; }
        .table td.number, .table th.number { text-align: right; }

        .note { font-size: 10px; line-height: 1.7; color: #6d655c; margin: 10mm 0 0 0; }
        .footer { position: fixed; bottom: 0; left: 0; width: 100%; text-align: center; font-size: 8px; letter-spacing: 3px; text-transform: uppercase; color: #6d655c; }
    </style>
</head>
<body>

<p class="eyebrow">{{ __('Rapport de durabilité') }}</p>
<h1>{{ $event->title }}</h1>
<p class="period">{{ $event->start_at->setTimezone($event->timezone)->translatedFormat('j F Y') }}</p>

<div class="rule"></div>

<table class="figures">
    <tr>
        <td>
            <p class="label">{{ __('Total') }}</p>
            <p class="value">{{ number_format($footprint->totalKilograms, 0, ',', ' ') }} kg</p>
        </td>
        <td>
            <p class="label">{{ __('Par participant') }}</p>
            <p class="value">{{ number_format($footprint->kilogramsPerAttendee(), 1, ',', ' ') }} kg</p>
        </td>
        <td>
            <p class="label">{{ __('Déclarations') }}</p>
            <p class="value">{{ $footprint->declarationRate() }} %</p>
        </td>
    </tr>
</table>

<h2>{{ __('D’où vient cette empreinte') }}</h2>

<table class="table">
    <tr>
        <th>{{ __('Source') }}</th>
        <th class="number">{{ __('kg CO2e') }}</th>
    </tr>
    <tr>
        <td>{{ __('Déplacements déclarés (aller-retour)') }}</td>
        <td class="number">{{ number_format($footprint->travelKilograms, 1, ',', ' ') }}</td>
    </tr>
    <tr>
        <td>{{ __('Repas servis') }} ({{ $footprint->mealsServed }})</td>
        <td class="number">{{ number_format($footprint->mealKilograms, 1, ',', ' ') }}</td>
    </tr>
    <tr>
        <td>{{ __('Impressions') }} ({{ $footprint->printedPages }} {{ __('pages') }})</td>
        <td class="number">{{ number_format($footprint->printKilograms, 1, ',', ' ') }}</td>
    </tr>
</table>

@if ($footprint->travelByMode !== [])
    <h2>{{ __('Comment les participants sont venus') }}</h2>

    <table class="table">
        <tr>
            <th>{{ __('Mode') }}</th>
            <th class="number">{{ __('Personnes') }}</th>
            <th class="number">{{ __('Kilomètres') }}</th>
            <th class="number">{{ __('kg CO2e') }}</th>
        </tr>
        @foreach ($footprint->travelByMode as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="number">{{ $row['people'] }}</td>
                <td class="number">{{ number_format($row['kilometres'], 0, ',', ' ') }}</td>
                <td class="number">{{ number_format($row['kilograms'], 1, ',', ' ') }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if ($carpoolSaving > 0)
    <h2>{{ __('Ce que le covoiturage ferait gagner') }}</h2>
    <p>
        {{ __('Si tous ceux qui sont venus seuls en voiture avaient partagé leur trajet, l’événement aurait pesé :amount kg de moins.', ['amount' => number_format($carpoolSaving, 1, ',', ' ')]) }}
        {{ __(':offers participants proposaient des places, :seekers en cherchaient une.', ['offers' => $footprint->carpoolOffers, 'seekers' => $footprint->carpoolSeekers]) }}
    </p>
@endif

<p class="note">
    {{ __('Les déplacements sont déclarés par les participants eux-mêmes : :declared sur :total ont répondu, et le total ne vaut que pour eux. Les facteurs d’émission employés sont des ordres de grandeur du même registre que la Base Carbone de l’ADEME — :meal kg par repas, :page g par page imprimée. Ce rapport éclaire une décision ; il ne tient pas lieu de bilan réglementaire.', [
        'declared' => $footprint->declaredCount,
        'total' => $footprint->attendeeCount,
        'meal' => number_format(\App\Support\Sustainability\GetEventCarbonFootprint::KILOGRAMS_PER_MEAL, 1, ',', ' '),
        'page' => number_format(\App\Support\Sustainability\GetEventCarbonFootprint::KILOGRAMS_PER_PAGE * 1000, 0, ',', ' '),
    ]) }}
</p>

<p class="footer">{{ $event->title }} &middot; {{ __('Empreinte carbone') }}</p>

</body>
</html>
