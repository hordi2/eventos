@extends('guest.layout')

@section('title', __('Sondages'))

@section('meta')
    @if ($polls !== [])
        {{-- Pas de temps réel (WebSocket) : la page se recharge toute seule,
             comme la salle d'attente de la billetterie (§2 du CLAUDE.md). --}}
        <meta http-equiv="refresh" content="8">
    @endif
@endsection

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-1 text-2xl">{{ __('Sondages') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">{{ __('Votez : les résultats se mettent à jour au fil des réponses.') }}</p>

        @if ($polls === [])
            <p class="text-sm text-ink-soft">{{ __("Aucun sondage ouvert pour le moment.") }}</p>
        @else
            <ul class="space-y-5">
                @foreach ($polls as $poll)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="mb-3 text-ink">{{ $poll['question'] }}</p>

                        <div class="space-y-2">
                            @foreach ($poll['options'] as $option)
                                <div>
                                    @if ($poll['status'] === 'open' && $poll['myOptionId'] === null)
                                        <form method="POST" action="{{ $option['voteUrl'] }}">
                                            @csrf
                                            <button type="submit" class="form-button inline-flex min-h-9 w-full items-center justify-start rounded-pill px-4 py-1.5 text-sm">
                                                {{ $option['label'] }}
                                            </button>
                                        </form>
                                    @else
                                        <div class="relative overflow-hidden rounded-pill bg-bg-alt px-4 py-1.5 text-sm ring-1 ring-line {{ $poll['myOptionId'] === $option['id'] ? 'text-ink' : 'text-ink-soft' }}">
                                            <div class="absolute inset-y-0 left-0 bg-accent/15" style="width: {{ $option['percent'] }}%"></div>
                                            <div class="relative flex items-center justify-between gap-3">
                                                <span>{{ $option['label'] }}@if ($poll['myOptionId'] === $option['id']) &nbsp;&middot;&nbsp;{{ __('votre choix') }}@endif</span>
                                                <span class="tabular-nums">{{ $option['percent'] }}%</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <p class="mt-3 text-xs text-ink-soft">
                            @if ($poll['status'] === 'closed')
                                {{ __('Sondage fermé') }} &middot;
                            @endif
                            {{ $poll['totalVotes'] }} {{ $poll['totalVotes'] > 1 ? __('votes') : __('vote') }}
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
