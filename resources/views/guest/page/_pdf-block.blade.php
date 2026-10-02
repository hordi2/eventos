{{-- Un feuillet du faire-part, pour un bloc de la page. Il porte le fond
     que l'organisateur lui a donné, comme sur le web : une image avec son
     voile, ou une couleur, et le texte en clair quand il le faut. --}}
@php
    $title = $block['title'] ?? null;
    $onDark = (bool) ($block['onDark'] ?? false);
    // Feuille de suite d'un bloc trop long pour une seule : son titre le dit.
    $suite = ($block['continued'] ?? false) ? ' '.__('(suite)') : '';
    // Pas de pied de page sur un feuillet déjà couvert d'une image : il
    // s'imprimerait par-dessus la photo.
    $hasBackground = ($block['backgroundImage'] ?? null) !== null
        || ($block['backgroundColor'] ?? null) !== null
        || $block['type'] === 'full_photo';
@endphp

<div class="page">
    {{-- Le filet gravé, sauf quand une photo couvre le feuillet. --}}
    @unless ($block['backgroundImage'] ?? null)
        @if ($block['type'] !== 'full_photo')
            <div class="frame {{ $onDark ? 'on-dark' : '' }}"></div>
        @endif
    @endunless

    @if ($block['backgroundColor'] ?? null)
        <div class="veil" style="background-color: {{ $block['backgroundColor'] }}"></div>
    @endif

    @if ($block['backgroundImage'] ?? null)
        <img src="{{ $block['backgroundImage'] }}" alt="" class="bleed">
        <div class="veil" style="background-color: rgba(20, 16, 12, {{ $block['overlay'] }})"></div>
    @endif

    @switch($block['type'])
        @case('full_photo')
            {{-- La photo prend le feuillet entier, le mot se lit en bas. --}}
            @if ($block['image'] ?? null)
                <img src="{{ $block['image'] }}" alt="" class="bleed">
                @if ($title || ! empty($block['body']))
                    <div class="photo-veil"></div>
                    <div class="photo-caption">
                        @if ($title)
                            <p class="script" style="color: #ffffff; margin: 0">{{ $title }}</p>
                        @endif
                        @if (! empty($block['body']))
                            <p style="font-size: 12px; margin: 3mm 0 0 0">{{ $block['body'] }}</p>
                        @endif
                    </div>
                @endif
            @endif
            @break

        @case('save_the_date')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <p class="eyebrow">{{ $invitation->month }}</p>

                <table class="calendar">
                    <thead>
                        <tr>
                            @foreach ([__('Lu'), __('Ma'), __('Me'), __('Je'), __('Ve'), __('Sa'), __('Di')] as $weekday)
                                <th>{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invitation->calendar['weeks'] as $week)
                            <tr>
                                @foreach ($week as $day)
                                    <td class="{{ $day === $invitation->calendar['highlight'] ? 'today' : '' }}">
                                        @if ($day !== null)
                                            {{ str_pad((string) $day, 2, '0', STR_PAD_LEFT) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <p class="script" style="margin-top: 10mm; font-size: 34px">{{ $title ?? __('Save the date') }}</p>

                @if (! empty($block['body']))
                    <p class="lead" style="margin-top: 8mm">{{ $block['body'] }}</p>
                @endif

                <table class="big-date">
                    <tr>
                        <td>{{ $invitation->shortMonth }}</td>
                        <td class="day">{{ $invitation->day }}</td>
                        <td>{{ $invitation->year }}</td>
                    </tr>
                </table>

                <p class="cover-meta" style="color: inherit; margin-top: 8mm">
                    {{ $invitation->time }}@if ($invitation->place) | {{ $invitation->place }}@endif
                </p>
            </div>
            @break

        @case('program')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __('Programme') }}{{ $suite }}</h2>
                <div class="ornament"></div>

                {{-- La même frise que la page web : le filet au milieu, les
                     moments de part et d'autre, en alternance. --}}
                <table class="programme">
                    @foreach ($block['items'] ?? [] as $index => $item)
                        @php($moment = ['item' => $item, 'showIcons' => (bool) ($block['showIcons'] ?? false), 'onDark' => $onDark])
                        <tr>
                            <td class="left">
                                @if ($index % 2 === 0)
                                    @include('guest.page._pdf-programme-item', $moment)
                                @endif
                            </td>
                            <td class="rail"><div class="dot"></div></td>
                            <td class="right">
                                @if ($index % 2 === 1)
                                    @include('guest.page._pdf-programme-item', $moment)
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @break

        @case('details')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                @if ($title)
                    <h2>{{ $title }}{{ $suite }}</h2>
                <div class="ornament"></div>
                @endif

                <table class="cards">
                    @foreach (array_chunk($block['items'] ?? [], 2) as $row)
                        <tr>
                            @foreach ($row as $item)
                                <td class="card">
                                    <p class="label">{{ $item['title'] ?? '' }}</p>
                                    @if (! empty($item['time']))
                                        <p class="value">{{ $item['time'] }}</p>
                                    @endif
                                    @if (! empty($item['description']))
                                        <p class="note" style="margin: 2mm 0 0 0; width: auto; font-size: 10px">{{ $item['description'] }}</p>
                                    @endif
                                </td>
                            @endforeach
                            @if (count($row) === 1)
                                <td style="border: none"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
            @break

        @case('gallery')
            @if (($block['photos'] ?? []) !== [])
                <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                    @if ($title)
                        <h2>{{ $title }}</h2>
                <div class="ornament"></div>
                    @endif

                    <table class="gallery">
                        @foreach (array_chunk($block['photos'], 2) as $row)
                            <tr>
                                @foreach ($row as $photo)
                                    <td>
                                        <img src="{{ $photo['image'] }}" alt="">
                                        @if (! empty($photo['description']))
                                            <p class="caption">{{ $photo['description'] }}</p>
                                        @endif
                                    </td>
                                @endforeach
                                @if (count($row) === 1)
                                    <td></td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
            @break

        @case('entry_qr')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __('Votre entrée') }}</h2>
                <div class="ornament"></div>

                @if ($invitation->entryQr)
                    <img src="{{ $invitation->entryQr }}" alt="" class="qr">
                    <p class="note">{{ $block['body'] ?: $invitation->entryNote }}</p>
                @else
                    <img src="{{ $invitation->rsvpQr }}" alt="" class="qr">
                    <p class="note">{{ __('Scannez ce code pour ouvrir votre invitation : votre code d\'entrée y apparaîtra une fois votre présence confirmée.') }}</p>
                @endif
            </div>
            @break

        @case('rsvp')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __('Confirmez votre présence') }}</h2>
                <div class="ornament"></div>

                @if ($invitation->guestName)
                    <p class="note" style="margin-top: 0">{{ __('Invitation adressée à :name', ['name' => $invitation->guestName]) }}</p>
                @endif

                <table class="answers">
                    <tr><td><div class="choice first">{{ $block['yesLabel'] ?: __('Je confirme ma présence') }}</div></td></tr>
                    @if ($invitation->declineEnabled)
                        <tr><td><div class="choice">{{ $block['noLabel'] ?: __('Je ne pourrai pas répondre présent') }}</div></td></tr>
                    @endif
                    <tr><td><div class="choice">{{ $block['laterLabel'] ?: __('Je vais confirmer plus tard') }}</div></td></tr>
                </table>

                <p class="note" style="margin-top: 8mm">{{ __('Scannez ce code, ou ouvrez le lien ci-dessous.') }}</p>
                <img src="{{ $invitation->rsvpQr }}" alt="" class="qr">
                <p class="link">{{ $invitation->rsvpUrl }}</p>
            </div>
            @break

        @case('image')
            @if ($block['image'] ?? null)
                <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                    @if ($title)
                        <h2>{{ $title }}</h2>
                <div class="ornament"></div>
                    @endif
                    <img src="{{ $block['image'] }}" alt="" style="width: 150mm">
                </div>
            @endif
            @break

        @case('venue')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __('Lieu') }}</h2>
                <div class="ornament"></div>
                <p class="lead">{{ $invitation->place }}</p>
                @if ($invitation->address)
                    <p class="note">{{ $invitation->address }}</p>
                @endif
            </div>
            @break

        @case('faq')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __('Questions fréquentes') }}{{ $suite }}</h2>
                <div class="ornament"></div>

                <table class="faq">
                    @foreach ($block['items'] ?? [] as $item)
                        <tr>
                            <td>
                                <p class="name">{{ $item['question'] ?? '' }}</p>
                                <p class="note">{{ $item['answer'] ?? '' }}</p>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @break

        @case('welcome_message')
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                <h2>{{ $title ?? __("Mot d'accueil") }}</h2>
                <div class="ornament"></div>
                @if (! empty($block['body']))
                    <p class="lead">{{ $block['body'] }}</p>
                @endif
                {{-- Un fichier encore en analyse antivirus n'a pas d'adresse :
                     le feuillet n'annonce alors aucun lien. --}}
                @if (! empty($block['url']))
                    <p class="note">{{ __('À écouter ou à regarder ici :') }}</p>
                    <p class="link">{{ $block['url'] }}</p>
                @endif
            </div>
            @break

        @default
            {{-- Texte, intervenants, sessions : un titre et ce qui se lit. --}}
            <div class="inner {{ $onDark ? 'on-dark' : '' }}">
                @if ($title)
                    <h2>{{ $title }}{{ $suite }}</h2>
                <div class="ornament"></div>
                @endif
                @if (! empty($block['body']))
                    <p class="lead">{{ $block['body'] }}</p>
                @endif
            </div>
    @endswitch

    {{-- Numéro de feuillet, comme la pagination de la page web. --}}
    @unless ($block['type'] === 'full_photo')
        <p class="folio {{ $onDark ? 'on-dark' : '' }}">{{ str_pad((string) $folio, 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $folioTotal, 2, '0', STR_PAD_LEFT) }}</p>
    @endunless
</div>
