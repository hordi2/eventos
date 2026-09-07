<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Itaza Invitation'))</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @yield('meta')
    @vite(['resources/css/guest.css', 'resources/js/guest.ts'])
</head>
<body class="flex min-h-screen flex-col bg-bg antialiased">
    <header class="border-b border-line">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5 sm:px-6">
            <a href="/">
                <img src="/images/logo.png" alt="{{ config('app.name', 'Itaza Invitation') }}" class="h-8 w-auto">
            </a>
            <nav class="flex items-center gap-5 font-label text-xs tracking-[0.14em] text-ink-soft uppercase">
                @auth
                    <a href="{{ route('dashboard') }}" class="hover:text-ink">Tableau de bord</a>
                @else
                    <a href="{{ route('login') }}" class="hover:text-ink">Se connecter</a>
                    <a href="{{ route('register') }}" class="hover:text-ink">S'inscrire</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    @include('guest.partials.footer')
</body>
</html>
