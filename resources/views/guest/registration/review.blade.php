@extends('guest.layout')

@section('title', "Inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Récapitulatif</h1>

        @include('guest.registration._progress', ['step' => 3])

        {{-- Soumission refusée (complet, fichier refusé par l'antivirus…) : tous les motifs. --}}
        @if ($errors->any())
            <div class="mb-6 rounded-control border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $message)
                    <p>{{ $message }}</p>
                @endforeach
            </div>
        @endif

        @php($identityUrl = route('guest.registration.identity.show', [request()->route('organization'), request()->route('event'), $draft->resume_token]))
        @php($answersUrl = route('guest.registration.answers.show', [request()->route('organization'), request()->route('event'), $draft->resume_token]))

        <div class="mb-6 rounded-card border border-line p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Identité</h2>
                <a href="{{ $identityUrl }}" class="text-xs text-accent underline">Modifier</a>
            </div>
            <p class="text-sm text-ink">{{ trim(($draft->identity['first_name'] ?? '').' '.($draft->identity['last_name'] ?? '')) ?: '—' }}</p>
            <p class="text-sm text-ink-soft">{{ ($draft->identity['email'] ?? '') !== '' ? $draft->identity['email'] : ($draft->identity['phone'] ?? '') }}</p>
            @if (! empty($draft->identity['phone']))
                <p class="text-sm text-ink-soft">{{ $draft->identity['phone'] }}</p>
            @endif
        </div>

        @if ($settings['rsvp']['decline_enabled'])
            <div class="mb-6 rounded-card border border-line p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Votre réponse</h2>
                    <a href="{{ $identityUrl }}" class="text-xs text-accent underline">Modifier</a>
                </div>
                <p class="text-sm text-ink">{{ $attending ? $settings['rsvp']['attending_label'] : $settings['rsvp']['decline_label'] }}</p>
            </div>
        @endif

        @if ($version->fields->isNotEmpty())
            <div class="mb-6 rounded-card border border-line p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Réponses</h2>
                    <a href="{{ $answersUrl }}" class="text-xs text-accent underline">Modifier</a>
                </div>
                @foreach ($version->fields as $field)
                    @continue(! $visibility[$field->key]['visible'] || $field->type->value === 'informational_text')
                    @php($raw = data_get($draft->answers, $field->key))
                    @continue(blank($raw) || (is_array($raw) && blank(array_filter($raw))))
                    <div class="mb-3 last:mb-0">
                        <p class="text-xs text-ink-soft">{{ $field->label }}</p>
                        <p class="text-sm text-ink">@include('guest.registration._answer_value', ['field' => $field, 'raw' => $raw])</p>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($companions !== [])
            <div class="mb-8 rounded-card border border-line p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Vos accompagnants</h2>
                    <a href="{{ $identityUrl }}" class="text-xs text-accent underline">Modifier</a>
                </div>
                @foreach ($companions as $companion)
                    <div class="mb-4 last:mb-0">
                        <p class="text-sm font-medium text-ink">{{ $companion['name'] }}</p>
                        @foreach ($version->fields as $field)
                            @continue(! \App\Domain\Form\Support\AskScope::isPerPerson($field) || ! $companion['visibility'][$field->key]['visible'])
                            @php($raw = $companion['answers'][$field->key] ?? null)
                            @continue($raw === null || $raw === '')
                            <p class="text-sm text-ink-soft">{{ $field->label }} : @include('guest.registration._answer_value', ['field' => $field, 'raw' => $raw])</p>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('guest.registration.review.confirm', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}">
            @csrf
            <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ $attending ? 'Confirmer mon inscription' : 'Envoyer ma réponse' }}</button>
        </form>

        <p class="mt-6 text-center text-xs text-ink-soft">
            Vous pouvez reprendre cette inscription plus tard grâce à ce lien : conservez-le.
        </p>
    </div>
@endsection
