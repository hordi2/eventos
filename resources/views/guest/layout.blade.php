<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Itaza Invitation'))</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @yield('meta')
    @vite(['resources/css/guest.css', 'resources/js/guest.ts'])
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
<body class="itaza-page min-h-screen bg-bg-alt antialiased">
    @if (isset($draft) && $draft instanceof \App\Domain\Form\Models\RegistrationDraft && $draft->is_test)
        {{-- Simulation lancée par « Prévisualiser » dans le constructeur. --}}
        <div role="status" class="sticky top-0 z-20 bg-ink px-4 py-2 text-center text-xs text-bg">
            Simulation d'inscription : rien ne sera enregistré et aucun message ne partira.
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
</body>
</html>
