@extends('guest.layout')

@section('title', __('Inscription').' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        @include('guest.registration._letterhead', ['event' => $event])
        <h1 class="mb-8 text-2xl">{{ __('Vos réponses') }}</h1>

        @include('guest.registration._progress', ['step' => 2])

        <form method="POST" action="{{ route('guest.registration.answers.store', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}" enctype="multipart/form-data" novalidate>
            @csrf

            @php
                $companionSections = collect($companions)->map(fn (array $companion) => [
                    ...$companion,
                    'fields' => $version->fields->filter(fn ($field) => \App\Domain\Form\Support\AskScope::isPerPerson($field) && $companion['visibility'][$field->key]['visible']),
                ]);
                $hasVisibleQuestion = collect($visibility)->contains(fn (array $state): bool => $state['visible'])
                    || $companionSections->contains(fn (array $section): bool => $section['fields']->isNotEmpty());
                // Bloc « Informations sur le donateur » prérempli avec le nom de l'invité.
                $donorDefaultName = trim(($draft->identity['first_name'] ?? '').' '.($draft->identity['last_name'] ?? ''));
            @endphp

            @unless ($hasVisibleQuestion)
                <p class="mb-8 text-ink-soft">{{ __('Aucune question supplémentaire : vous pouvez continuer.') }}</p>
            @endunless

            @foreach ($version->fields as $field)
                @if ($visibility[$field->key]['visible'])
                    @include('guest.registration._field', ['field' => $field, 'value' => data_get($draft->answers, $field->key), 'donorDefaultName' => $donorDefaultName])
                @endif
            @endforeach

            {{-- Questions posées à chaque personne : une section par accompagnant (T-032). --}}
            @foreach ($companionSections as $index => $section)
                @continue($section['fields']->isEmpty())
                <section class="mb-8 rounded-card border border-line p-4">
                    <h2 class="mb-4 text-lg">{{ __('Pour :name', ['name' => $section['name']]) }}</h2>
                    @foreach ($section['fields'] as $field)
                        @include('guest.registration._field', [
                            'field' => $field,
                            'value' => $section['answers'][$field->key] ?? null,
                            'namePrefix' => "_companions[{$index}][answers]",
                        ])
                    @endforeach
                </section>
            @endforeach

            <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ __('Continuer') }}</button>
        </form>
    </div>
@endsection
