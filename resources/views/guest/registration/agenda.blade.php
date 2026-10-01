@extends('guest.layout')

@section('title', __('Mon agenda').' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        @include('guest.registration._letterhead', ['event' => $event])
        <h1 class="mb-2 text-2xl">{{ __('Mon agenda') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">
            {{ __('Le programme de :name.', ['name' => trim($registration->first_name.' '.$registration->last_name) ?: $registration->email]) }}
        </p>

        @if ($sessions === [])
            <div class="rounded-card border border-dashed border-line bg-bg px-6 py-12 text-center">
                <p class="mb-1 font-serif text-xl text-ink italic">{{ __('Aucune session choisie') }}</p>
                <p class="text-sm text-ink-soft">{{ __("Vous êtes bien inscrit à l'événement : c'est le programme détaillé qui reste à composer.") }}</p>
            </div>
        @else
            @php($currentDay = null)
            <ul class="space-y-3">
                @foreach ($sessions as $session)
                    @if ($session['day'] !== $currentDay)
                        @php($currentDay = $session['day'])
                        <li class="pt-4 first:pt-0">
                            <h2 class="font-serif text-xl italic">{{ $currentDay }}</h2>
                        </li>
                    @endif
                    <li class="rounded-card border border-line bg-bg px-4 py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="text-ink">{{ $session['title'] }}</p>
                            <p class="text-sm font-medium text-ink">{{ $session['time'] }}</p>
                        </div>
                        @if ($session['room'] || $session['speakers'] !== [])
                            <p class="text-sm text-ink-soft">
                                @if ($session['room']){{ $session['room'] }}@endif
                                @if ($session['room'] && $session['speakers'] !== []) · @endif
                                @if ($session['speakers'] !== []){{ __('avec :names', ['names' => implode(', ', $session['speakers'])]) }}@endif
                            </p>
                        @endif
                        @if ($session['isWaitlisted'])
                            <p class="mt-2 text-sm text-ink-soft">{{ __("Vous êtes sur la liste d'attente de cette session : nous vous prévenons si une place se libère.") }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-8">
                <a href="{{ $icsUrl }}" class="inline-flex min-h-11 items-center rounded-pill border border-line px-5 py-2.5 text-sm font-medium text-ink">
                    {{ __('Ajouter mon programme à mon agenda (.ics)') }}
                </a>
            </div>
        @endif

        <p class="mt-10 text-center text-xs text-ink-soft">{{ __('Ce lien est personnel : conservez-le pour retrouver votre programme le jour J.') }}</p>
    </div>
@endsection
