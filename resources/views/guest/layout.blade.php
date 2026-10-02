<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Itaza Invitation'))</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @yield('meta')
    @vite(['resources/css/guest.css', 'resources/js/guest.ts'])
    {{-- Polices de l'invitation : seules les familles choisies sont
         chargées, et seulement dans les graisses employées. Sans ce lien,
         la page retombait sur le Times du système. --}}
    @isset($page)
        @if ($page->fontStylesheet)
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="{{ $page->fontStylesheet }}" rel="stylesheet">
        @endif
        <style>
            :root {
                --font-serif: {!! $page->headingFont !!};
                --font-sans: {!! $page->bodyFont !!};
                --font-script: {!! $page->scriptFont !!};
            }
        </style>
    @endisset
    @isset($formTheme)
        {{-- Thème du formulaire : couleurs revérifiées, polices d'une liste
             fermée (FormSettings::cssVariables) et URL d'image générée par
             Itaza. Aucune valeur libre : l'affichage brut est sans risque, et
             nécessaire, l'échappement HTML cassant les guillemets en CSS. --}}
        <style>
            :root {
                @foreach ($formTheme['variables'] as $name => $value)
                    {{ $name }}: {!! $value !!};
                @endforeach
            }
            @if ($formTheme['backgroundUrl'])
                body {
                    background-image: url('{!! $formTheme['backgroundUrl'] !!}');
                    background-size: cover;
                    background-position: center;
                    background-attachment: fixed;
                }
            @endif
        </style>
        @if ($formTheme['customCssUrl'] ?? null)
            {{-- CSS de l'organisateur : servi à part, vérifié avant livraison
                 (CustomCss), et placé en dernier pour qu'il puisse ajuster le
                 thème sans être écrasé par lui. --}}
            <link rel="stylesheet" href="{{ $formTheme['customCssUrl'] }}">
        @endif
    @endisset
</head>
{{-- itaza-page, itaza-header, itaza-field et itaza-progress sont les repères offerts au
     CSS personnalisé : ce sont des noms stables, contrairement aux classes
     utilitaires qui changent à chaque construction des styles. --}}
<body class="itaza-page min-h-screen bg-bg-alt antialiased @yield('bodyClass')">
    @if (isset($draft) && $draft instanceof \App\Domain\Form\Models\RegistrationDraft && $draft->is_test)
        {{-- Simulation lancée par « Prévisualiser » dans le constructeur. --}}
        <div role="status" class="sticky top-0 z-20 bg-ink px-4 py-2 text-center text-xs text-bg">
            {{ __("Simulation d'inscription : rien ne sera enregistré et aucun message ne partira.") }}
        </div>
    @endif
    @php($headerUrl = isset($formTheme) ? ($formTheme['headerUrl'] ?? null) : null)
    @if ($headerUrl)
        {{-- Bandeau du formulaire, au-dessus de tout : chargé en priorité, il
             est la première chose que voit l'invité (image déjà réduite à l'envoi). --}}
        <div class="itaza-header">
            <img src="{{ $headerUrl }}" alt="" fetchpriority="high" class="h-44 w-full object-cover sm:h-64">
        </div>
    @endif
    @if (isset($formTheme) && $formTheme['logoUrl'])
        <div class="relative flex justify-center px-4 {{ $headerUrl ? '-mt-10' : 'pt-10' }}">
            <img src="{{ $formTheme['logoUrl'] }}" alt="" class="h-14 w-auto max-w-[240px] object-contain {{ $headerUrl ? 'h-20 rounded-card bg-bg p-2 shadow-sm' : '' }}">
        </div>
    @endif
    @yield('content')

    @php($ga4 = isset($guestOrganization) ? $guestOrganization->ga4_measurement_id : null)
    @if ($ga4)
        {{-- Mesure d'audience : chargée seulement si l'invité l'accepte
             (§10.2, RGPD). Son choix est gardé sur son appareil, jamais
             chez nous, et rien ne part avant qu'il ne l'ait donné. --}}
        <div
            x-data="{
                choice: localStorage.getItem('itaza-audience'),
                accept() { this.choice = 'yes'; localStorage.setItem('itaza-audience', 'yes'); this.load(); },
                refuse() { this.choice = 'no'; localStorage.setItem('itaza-audience', 'no'); },
                load() {
                    if (document.getElementById('itaza-ga4')) { return; }
                    const script = document.createElement('script');
                    script.id = 'itaza-ga4';
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}';
                    document.head.appendChild(script);
                    window.dataLayer = window.dataLayer || [];
                    window.gtag = function () { window.dataLayer.push(arguments); };
                    window.gtag('js', new Date());
                    window.gtag('config', '{{ $ga4 }}', { anonymize_ip: true });
                },
            }"
            x-init="if (choice === 'yes') { load(); }"
        >
            <div x-show="choice === null" x-cloak class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-bg px-4 py-3">
                <div class="mx-auto flex max-w-lg flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-ink-soft">{{ __("Acceptez-vous la mesure d'audience de cette page ? Elle aide l'organisateur à savoir combien de personnes l'ont vue.") }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="refuse()" class="min-h-9 rounded-pill border border-line px-4 py-1.5 text-xs text-ink">{{ __('Refuser') }}</button>
                        <button type="button" @click="accept()" class="min-h-9 rounded-pill bg-ink px-4 py-1.5 text-xs text-bg">{{ __('Accepter') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Sélecteur de langue (lot 2) : l'invité peut passer d'une langue à
         l'autre, son choix est retenu pour la suite du parcours. --}}
    @if (count(\App\Support\Guest\GuestLocales::SUPPORTED) > 1)
        <div class="mx-auto max-w-lg px-4 pb-10 text-center text-xs text-ink-soft">
            @foreach (\App\Support\Guest\GuestLocales::SUPPORTED as $code => $name)
                @if ($code === app()->getLocale())
                    <span class="px-2 font-medium text-ink">{{ $name }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['lang' => $code]) }}" class="px-2 underline hover:no-underline" hreflang="{{ $code }}">{{ $name }}</a>
                @endif
            @endforeach
        </div>
    @endif
</body>
</html>
