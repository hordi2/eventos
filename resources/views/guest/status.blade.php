@extends('guest.static-layout')

@section('title', 'Statut — ' . config('app.name', 'Itaza Invitation'))

@php
    $componentLabels = [
        'database' => 'Base de données',
        'redis' => 'File d\'attente et cache',
    ];
@endphp

@section('content')
    <div class="border-b border-line bg-accent/5">
        <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
            <p class="mb-3 font-label text-xs tracking-[0.28em] text-accent uppercase">Supervision</p>
            <h1 class="font-serif text-4xl text-ink italic">Statut de la plateforme</h1>
        </div>
    </div>

    <div class="mx-auto max-w-2xl px-4 py-16 sm:px-6">
        <div class="mb-8 flex items-center gap-3 rounded-lg border p-4 {{ $isHealthy ? 'border-green-300 bg-green-50' : 'border-red-300 bg-red-50' }}">
            <span class="h-3 w-3 shrink-0 rounded-full {{ $isHealthy ? 'bg-green-500' : 'bg-red-500' }}"></span>
            <p class="font-medium {{ $isHealthy ? 'text-green-800' : 'text-red-800' }}">
                {{ $isHealthy ? 'Tous les systèmes sont opérationnels' : 'Un incident est en cours' }}
            </p>
        </div>

        @if ($uptimePercentage !== null)
            <p class="mb-6 text-sm text-ink-soft">
                Disponibilité sur les dernières 24 heures : {{ $uptimePercentage }} %
            </p>
        @endif

        @if (! empty($components))
            <ul class="mb-8 divide-y rounded-lg border">
                @foreach ($components as $component => $ok)
                    <li class="flex items-center justify-between px-4 py-3">
                        <span>{{ $componentLabels[$component] ?? $component }}</span>
                        <span class="{{ $ok ? 'text-green-700' : 'text-red-700' }}">
                            {{ $ok ? 'Opérationnel' : 'En échec' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($checkedAt)
            <p class="text-xs text-ink-soft">Dernière vérification : {{ $checkedAt->timezone(config('app.timezone'))->translatedFormat('d/m/Y à H:i') }}</p>
        @endif

        @if ($recentChecks->isNotEmpty())
            <h2 class="mb-3 mt-10 text-sm font-medium text-ink-soft">Historique récent</h2>
            <div class="flex gap-1">
                @foreach ($recentChecks->reverse() as $check)
                    <span
                        class="h-6 flex-1 rounded {{ $check->is_healthy ? 'bg-green-400' : 'bg-red-400' }}"
                        title="{{ $check->checked_at->timezone(config('app.timezone'))->translatedFormat('d/m/Y à H:i') }}"
                    ></span>
                @endforeach
            </div>
        @endif
    </div>
@endsection
