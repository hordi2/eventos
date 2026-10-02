{{-- Un moment du programme sur le faire-part : l'illustration, l'heure,
     l'intitulé, le détail — la même pile que sur la page web. --}}
@if ($showIcons)
    @if (! empty($item['iconImage']))
        {{-- L'illustration déposée par l'organisateur. --}}
        <div class="icon"><img src="{{ $item['iconImage'] }}" alt="" width="26" height="26"></div>
    @else
        {{-- dompdf ne dessine pas un <svg> posé dans la page, mais sait lire
             une image SVG : le dessin part donc en data URI. --}}
        @php($iconSvg = view('guest.page._programme-icon', [
            'icon' => $item['icon'] ?? null,
            'color' => $onDark ? '#ffffff' : '#1b1611',
        ])->render())
        <div class="icon"><img src="data:image/svg+xml;base64,{{ base64_encode($iconSvg) }}" alt="" width="26" height="26"></div>
    @endif
@endif

<p class="hour">{{ $item['time'] ?? '' }}</p>
<p class="name">{{ $item['title'] ?? '' }}</p>

@if (! empty($item['description']))
    <p class="note">{{ $item['description'] }}</p>
@endif
