@extends('guest.layout')

@section('title', "Inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Votre inscription</h1>

        @include('guest.registration._progress', ['step' => 1])

        @php
            $declineEnabled = (bool) $settings['rsvp']['decline_enabled'];
            $maxCompanions = (int) $settings['rsvp']['max_companions'];
            $attendingChoice = (string) old('attending', ($draft->identity['attending'] ?? true) ? '1' : '0');
            $companionRows = array_values(old('_companions', $draft->identity['companions'] ?? []));
        @endphp

        <form
            method="POST"
            action="{{ route('guest.registration.identity.store', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}"
            novalidate
            x-data="{ attending: '{{ $attendingChoice === '0' ? '0' : '1' }}', companions: {{ count($companionRows) }} }"
        >
            @csrf

            <div class="mb-6">
                <label for="email" class="mb-1.5 block text-sm font-medium text-ink">Adresse e-mail *</label>
                <input type="email" id="email" name="email" value="{{ old('email', $draft->identity['email'] ?? '') }}" required autofocus class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('email')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6 grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="mb-1.5 block text-sm font-medium text-ink">Prénom</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $draft->identity['first_name'] ?? '') }}" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
                <div>
                    <label for="last_name" class="mb-1.5 block text-sm font-medium text-ink">Nom</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $draft->identity['last_name'] ?? '') }}" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                </div>
            </div>

            @php $phoneRequired = $event->type->category() === \App\Domain\Event\Models\EventCategory::Personal; @endphp
            <div class="mb-8">
                <label for="phone" class="mb-1.5 block text-sm font-medium text-ink">Téléphone{{ $phoneRequired ? ' *' : '' }}</label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $draft->identity['phone'] ?? '') }}" placeholder="+243 8xx xxx xxx" @required($phoneRequired) class="w-full rounded-control border border-line px-3 py-2 text-ink">
                @error('phone')
                    <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            @if ($declineEnabled)
                <fieldset class="mb-8">
                    <legend class="mb-2 block text-sm font-medium text-ink">Votre réponse *</legend>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 rounded-control border border-line bg-bg px-4 py-3 text-ink">
                            <input type="radio" name="attending" value="1" x-model="attending" @checked($attendingChoice === '1') required>
                            {{ $settings['rsvp']['attending_label'] }}
                        </label>
                        <label class="flex items-center gap-3 rounded-control border border-line bg-bg px-4 py-3 text-ink">
                            <input type="radio" name="attending" value="0" x-model="attending" @checked($attendingChoice === '0')>
                            {{ $settings['rsvp']['decline_label'] }}
                        </label>
                    </div>
                    @error('attending')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>
            @endif

            {{-- Accompagnants (T-032). Sans JavaScript, toutes les lignes
                 s'affichent et une ligne vide est ignorée ; avec, on n'en
                 montre qu'autant que l'invité en ajoute. --}}
            @if ($maxCompanions > 0)
                <fieldset class="mb-8" x-show="attending !== '0'">
                    <legend class="mb-1 block text-sm font-medium text-ink">Vos accompagnants</legend>
                    <p class="mb-4 text-sm text-ink-soft">
                        Vous pouvez venir avec {{ $maxCompanions }} {{ $maxCompanions > 1 ? 'personnes' : 'personne' }} au plus. Chacune compte pour une place et reçoit son propre QR code.
                    </p>

                    @error('_companions')
                        <p class="mb-3 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    @for ($i = 0; $i < $maxCompanions; $i++)
                        <div class="mb-4" x-show="{{ $i }} < companions" x-ref="companion{{ $i }}">
                            <p class="mb-1.5 text-xs font-medium tracking-wide text-ink-soft uppercase">Accompagnant {{ $i + 1 }}</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label for="companion_{{ $i }}_first_name" class="sr-only">Prénom de l'accompagnant {{ $i + 1 }}</label>
                                    <input type="text" id="companion_{{ $i }}_first_name" name="_companions[{{ $i }}][first_name]" value="{{ $companionRows[$i]['first_name'] ?? '' }}" placeholder="Prénom" autocomplete="off" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                                </div>
                                <div>
                                    <label for="companion_{{ $i }}_last_name" class="sr-only">Nom de l'accompagnant {{ $i + 1 }}</label>
                                    <input type="text" id="companion_{{ $i }}_last_name" name="_companions[{{ $i }}][last_name]" value="{{ $companionRows[$i]['last_name'] ?? '' }}" placeholder="Nom" autocomplete="off" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                                </div>
                            </div>
                            @error("_companions.{$i}.first_name")
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endfor

                    <div class="flex flex-wrap gap-5 text-sm">
                        <button type="button" x-cloak x-show="companions < {{ $maxCompanions }}" x-on:click="companions++" class="font-medium text-accent underline">
                            + Ajouter un accompagnant
                        </button>
                        <button
                            type="button"
                            x-cloak
                            x-show="companions > 0"
                            x-on:click="$refs['companion' + (companions - 1)].querySelectorAll('input').forEach((input) => input.value = ''); companions--"
                            class="text-ink-soft underline"
                        >
                            Retirer le dernier
                        </button>
                    </div>
                </fieldset>
            @endif

            <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">Continuer</button>
        </form>

        <p class="mt-6 text-center text-xs text-ink-soft">
            Vous pouvez reprendre cette inscription plus tard grâce à ce lien : conservez-le.
        </p>
    </div>
@endsection
