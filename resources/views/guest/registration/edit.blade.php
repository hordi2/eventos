@extends('guest.layout')

@section('title', "Modifier mon inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Modifier mon inscription</h1>

        @error('submission')
            <p class="mb-6 rounded-control border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ url()->full() }}" novalidate>
            @csrf

            <div class="mb-6">
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">Adresse e-mail *</label>
                <input type="email" id="email" name="email" value="{{ old('email', $registration->email) }}" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="mb-1.5 block text-sm font-medium text-ink">Prénom</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $registration->first_name) }}" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div>
                    <label for="last_name" class="mb-1.5 block text-sm font-medium text-ink">Nom</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $registration->last_name) }}" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
            </div>

            @php $phoneRequired = $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal; @endphp
            <div class="mb-8">
                <label for="phone" class="mb-1.5 block text-sm font-medium text-ink">Téléphone{{ $phoneRequired ? ' *' : '' }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $registration->phone_e164) }}" @required($phoneRequired) class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('phone')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @foreach ($version->fields as $field)
                @continue(! $visibility[$field->key]['visible'])
                {{-- Le don a donné naissance à une commande : il ne se modifie pas ici (T-056). --}}
                @if ($field->type->isLockedAfterSubmission())
                    @if ($field->type->value === 'donation' && ($donationAmount = \App\Domain\Form\Support\DonationAnswer::stored(data_get($answers, $field->key))) !== null)
                        <p class="mb-6 rounded-control border border-line px-4 py-3 text-sm text-ink-soft">Votre don de <strong class="text-ink">{{ $donationAmount->format() }}</strong> ne se modifie pas ici.</p>
                    @endif
                @else
                    @include('guest.registration._field', ['field' => $field, 'value' => data_get($answers, $field->key)])
                @endif
            @endforeach

            {{-- Accompagnants (T-032) : leur nombre ne change pas ici, seulement leurs noms et réponses. --}}
            @foreach ($companions as $index => $companion)
                <section class="mb-8 rounded-card border border-line p-4">
                    <h2 class="mb-4 text-lg">Accompagnant {{ $index + 1 }}</h2>
                    <div class="mb-6 grid grid-cols-2 gap-4">
                        <div>
                            <label for="companion_{{ $index }}_first_name" class="mb-1.5 block text-sm font-medium text-ink">Prénom *</label>
                            <input type="text" id="companion_{{ $index }}_first_name" name="_companions[{{ $index }}][first_name]" value="{{ old("_companions.{$index}.first_name", $companion['firstName']) }}" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                            @error("_companions.{$index}.first_name")
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="companion_{{ $index }}_last_name" class="mb-1.5 block text-sm font-medium text-ink">Nom</label>
                            <input type="text" id="companion_{{ $index }}_last_name" name="_companions[{{ $index }}][last_name]" value="{{ old("_companions.{$index}.last_name", $companion['lastName']) }}" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                        </div>
                    </div>
                    @foreach ($version->fields as $field)
                        @if (\App\Domain\Form\Support\AskScope::isPerPerson($field) && $companion['visibility'][$field->key]['visible'])
                            @include('guest.registration._field', [
                                'field' => $field,
                                'value' => $companion['answers'][$field->key] ?? null,
                                'namePrefix' => "_companions[{$index}][answers]",
                            ])
                        @endif
                    @endforeach
                </section>
            @endforeach

            <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">Enregistrer les modifications</button>
        </form>
    </div>
@endsection
