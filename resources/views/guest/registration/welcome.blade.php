@extends('guest.layout')

@section('title', "Bienvenue — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center sm:py-20">
        <p class="mb-2 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-5 text-3xl">{{ $settings['welcome']['title'] !== '' ? $settings['welcome']['title'] : $event->title }}</h1>

        @if ($settings['welcome']['message'] !== '')
            <p class="mb-10 whitespace-pre-line text-ink-soft">{{ $settings['welcome']['message'] }}</p>
        @endif

        <a
            href="{{ route('guest.registration.identity.show', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}"
            class="form-button inline-flex min-h-11 items-center justify-center rounded-pill px-10 py-3 font-medium"
        >{{ $settings['welcome']['button_label'] !== '' ? $settings['welcome']['button_label'] : 'Commencer' }}</a>
    </div>
@endsection
