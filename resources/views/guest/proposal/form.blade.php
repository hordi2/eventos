@extends('guest.layout')

@section('title', __('Appel à contributions').' — '.$event->title)

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-2 text-2xl">{{ __('Appel à contributions') }}</h1>
        <p class="mb-6 text-sm text-ink-soft">{{ $schedule }}</p>

        @if (session('status') === 'proposal-submitted')
            <p role="status" class="mb-8 rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">
                {{ __("Votre sujet est bien arrivé. Vous recevez un accusé de réception par e-mail, et la réponse de l'organisateur dès que le programme sera arrêté.") }}
            </p>
        @endif

        @if ($call->intro)
            <p class="mb-8 text-sm whitespace-pre-line text-ink">{{ $call->intro }}</p>
        @endif

        @if (! $call->acceptsProposals())
            <p class="rounded-card border border-dashed border-line bg-bg px-6 py-10 text-center text-ink-soft">
                {{ __("L'appel à contributions est clos : les propositions ne sont plus reçues.") }}
            </p>
        @else
            @if ($call->closes_at)
                <p class="mb-8 text-sm text-ink-soft">
                    {{ __('Vous avez jusqu\'au :date.', ['date' => $call->closes_at->setTimezone($event->timezone)->translatedFormat('l j F Y \à H\hi')]) }}
                </p>
            @endif

            <form method="POST" action="{{ $submitUrl }}" novalidate>
                @csrf

                <h2 class="mb-4 font-serif text-xl italic">{{ __('Votre sujet') }}</h2>

                <div class="mb-5">
                    <label for="title" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Titre') }} *</label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    @error('title')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="summary" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Résumé') }} *</label>
                    <p class="mb-2 text-xs text-ink-soft">{{ __('De quoi parlerez-vous, et à qui cela s\'adresse-t-il ?') }}</p>
                    <textarea id="summary" name="summary" rows="6" required maxlength="5000" class="w-full rounded-control border border-line px-3 py-2 text-ink">{{ old('summary') }}</textarea>
                    @error('summary')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-8 grid grid-cols-2 gap-4">
                    <div>
                        <label for="format" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Forme') }} *</label>
                        <select id="format" name="format" required class="w-full rounded-control border border-line px-3 py-2 text-ink">
                            @foreach ($formats as $format)
                                <option value="{{ $format->value }}" @selected(old('format') === $format->value)>{{ __($format->label()) }}</option>
                            @endforeach
                        </select>
                        @error('format')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="duration_minutes" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Durée souhaitée') }}</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" value="{{ old('duration_minutes') }}" min="5" max="480" step="5" placeholder="30" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                        <p class="mt-1 text-xs text-ink-soft">{{ __('En minutes.') }}</p>
                        @error('duration_minutes')
                            <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <h2 class="mb-4 font-serif text-xl italic">{{ __('Vous') }}</h2>

                <div class="mb-5">
                    <label for="proposer_name" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Nom') }} *</label>
                    <input type="text" id="proposer_name" name="proposer_name" value="{{ old('proposer_name') }}" required maxlength="255" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    @error('proposer_name')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="proposer_email" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Adresse e-mail') }} *</label>
                    <input type="email" id="proposer_email" name="proposer_email" value="{{ old('proposer_email') }}" required maxlength="255" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    <p class="mt-1 text-xs text-ink-soft">{{ __("C'est là que vous recevrez la réponse.") }}</p>
                    @error('proposer_email')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5 grid grid-cols-2 gap-4">
                    <div>
                        <label for="proposer_role" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Fonction') }}</label>
                        <input type="text" id="proposer_role" name="proposer_role" value="{{ old('proposer_role') }}" maxlength="255" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    </div>
                    <div>
                        <label for="proposer_company" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Organisation') }}</label>
                        <input type="text" id="proposer_company" name="proposer_company" value="{{ old('proposer_company') }}" maxlength="255" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    </div>
                </div>

                <div class="mb-8">
                    <label for="proposer_bio" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Biographie') }}</label>
                    <p class="mb-2 text-xs text-ink-soft">{{ __('Quelques lignes : elles serviront à vous présenter si votre sujet est retenu.') }}</p>
                    <textarea id="proposer_bio" name="proposer_bio" rows="4" maxlength="2000" class="w-full rounded-control border border-line px-3 py-2 text-ink">{{ old('proposer_bio') }}</textarea>
                    @error('proposer_bio')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ __('Proposer mon sujet') }}</button>
            </form>
        @endif
    </div>
@endsection
