@extends('guest.layout')

@section('title', "Inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Récapitulatif</h1>

        @include('guest.registration._progress', ['step' => 3])

        @error('submission')
            <p class="mb-6 rounded-control border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <div class="mb-6 rounded-card border border-line p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Identité</h2>
                <a href="{{ route('guest.registration.identity.show', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}" class="text-xs text-accent underline">Modifier</a>
            </div>
            <p class="text-sm text-ink">{{ trim(($draft->identity['first_name'] ?? '').' '.($draft->identity['last_name'] ?? '')) ?: '—' }}</p>
            <p class="text-sm text-ink-soft">{{ $draft->identity['email'] ?? '' }}</p>
            @if (! empty($draft->identity['phone']))
                <p class="text-sm text-ink-soft">{{ $draft->identity['phone'] }}</p>
            @endif
        </div>

        @if ($version->fields->isNotEmpty())
            <div class="mb-8 rounded-card border border-line p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Réponses</h2>
                    <a href="{{ route('guest.registration.answers.show', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}" class="text-xs text-accent underline">Modifier</a>
                </div>
                @foreach ($version->fields as $field)
                    @continue(! $visibility[$field->key]['visible'] || $field->type->value === 'informational_text')
                    @php($raw = data_get($draft->answers, $field->key))
                    @continue($raw === null || $raw === '')
                    <div class="mb-3 last:mb-0">
                        <p class="text-xs text-ink-soft">{{ $field->label }}</p>
                        <p class="text-sm text-ink">
                            @switch($field->type->value)
                                @case('single_choice')
                                @case('meal_choice')
                                    {{ $field->options->firstWhere('value', $raw)?->label ?? $raw }}
                                    @break
                                @case('multiple_choice')
                                    {{ $field->options->whereIn('value', (array) $raw)->pluck('label')->implode(', ') }}
                                    @break
                                @case('yes_no')
                                    {{ $raw === '1' || $raw === 1 ? 'Oui' : 'Non' }}
                                    @break
                                @case('consent')
                                    Accepté
                                    @break
                                @default
                                    {{ $raw }}
                            @endswitch
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('guest.registration.review.confirm', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}">
            @csrf
            <button type="submit" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">Confirmer mon inscription</button>
        </form>

        <p class="mt-6 text-center text-xs text-ink-soft">
            Vous pouvez reprendre cette inscription plus tard grâce à ce lien : conservez-le.
        </p>
    </div>
@endsection
