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
{{-- itaza-page, itaza-field et itaza-progress sont les repères offerts au
     CSS personnalisé : ce sont des noms stables, contrairement aux classes
     utilitaires qui changent à chaque construction des styles. --}}
<body class="itaza-page min-h-screen bg-bg-alt antialiased">
    @if (isset($formTheme) && $formTheme['logoUrl'])
        <div class="flex justify-center px-4 pt-10">
            <img src="{{ $formTheme['logoUrl'] }}" alt="" class="h-14 w-auto max-w-[240px] object-contain">
        </div>
    @endif
    @yield('content')
</body>
</html>
