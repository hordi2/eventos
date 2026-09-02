<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Itaza Invitation'))</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    @vite(['resources/css/guest.css', 'resources/js/guest.ts'])
</head>
<body class="min-h-screen bg-bg-alt antialiased">
    @yield('content')
</body>
</html>
