@extends('guest.layout')

@section('title', "Inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Vos réponses</h1>

        @include('guest.registration._progress', ['step' => 2])

        <form method="POST" action="{{ route('guest.registration.answers.store', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}" novalidate>
            @csrf

            @foreach ($version->fields as $field)
                @if ($visibility[$field->key]['visible'])
                    @include('guest.registration._field', ['field' => $field, 'value' => data_get($draft->answers, $field->key)])
                @endif
            @endforeach

            <button type="submit" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">Continuer</button>
        </form>
    </div>
@endsection
