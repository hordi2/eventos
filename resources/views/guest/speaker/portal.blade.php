@extends('guest.layout')

@section('title', __('Espace intervenant').' — '.$portal['event']['title'])

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $portal['event']['title'] }}</p>
        <h1 class="mb-2 text-2xl">{{ __('Bonjour :name', ['name' => $portal['name']]) }}</h1>
        <p class="mb-8 text-sm text-ink-soft">
            {{ $portal['event']['schedule'] }}@if ($portal['event']['place']) · {{ $portal['event']['place'] }}@endif
        </p>

        @if (session('status') === 'speaker-response-saved')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">{{ __('Votre réponse est enregistrée. Merci.') }}</p>
        @elseif (session('status') === 'speaker-support-saved')
            <p role="status" class="mb-6 rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">{{ __('Votre support est bien arrivé. Il est vérifié avant d\'être transmis à l\'organisateur.') }}</p>
        @endif

        <section class="mb-10">
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Votre créneau') }}</h2>

            @if ($portal['sessions'] === [])
                <p class="rounded-card border border-dashed border-line bg-bg px-4 py-6 text-sm text-ink-soft">
                    {{ __("L'organisateur n'a pas encore fixé votre horaire : il vous le communiquera ici.") }}
                </p>
            @else
                <ul class="space-y-2">
                    @foreach ($portal['sessions'] as $session)
                        <li class="rounded-card border border-line bg-bg px-4 py-3">
                            <p class="text-ink">{{ $session['title'] }}</p>
                            <p class="text-sm text-ink-soft">
                                {{ $session['schedule'] }}@if ($session['room']) · {{ $session['room'] }}@endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($portal['status'] === 'confirmed')
                <p class="mt-4 rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">{{ __('Vous avez confirmé votre participation.') }}</p>
            @elseif ($portal['status'] === 'declined')
                <p class="mt-4 rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">{{ __("Vous avez indiqué que vous ne pourrez pas être là. L'organisateur en est informé.") }}</p>
            @endif

            <form method="POST" action="{{ $respondUrl }}" class="mt-6">
                @csrf

                <fieldset class="mb-5">
                    <legend class="mb-2 block text-sm font-medium text-ink">
                        {{ $portal['status'] === 'pending' ? __('Confirmez-vous ce créneau ?') : __('Changer votre réponse') }}
                    </legend>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 rounded-control border border-line bg-bg px-4 py-3 text-ink">
                            <input type="radio" name="response" value="accept" @checked(old('response', $portal['status'] === 'confirmed' ? 'accept' : null) === 'accept') required>
                            {{ __('Oui, je serai là') }}
                        </label>
                        <label class="flex items-center gap-3 rounded-control border border-line bg-bg px-4 py-3 text-ink">
                            <input type="radio" name="response" value="decline" @checked(old('response', $portal['status'] === 'declined' ? 'decline' : null) === 'decline')>
                            {{ __('Non, je ne pourrai pas') }}
                        </label>
                    </div>
                    @error('response')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="mb-5">
                    <label for="note" class="mb-1.5 block text-sm font-medium text-ink">{{ __("Un message pour l'organisateur (facultatif)") }}</label>
                    <textarea id="note" name="note" rows="3" maxlength="1000" class="w-full rounded-control border border-line px-3 py-2 text-ink">{{ old('note', $portal['responseNote']) }}</textarea>
                    @error('note')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ __('Envoyer ma réponse') }}</button>
            </form>
        </section>

        <section class="mb-10">
            <h2 class="mb-3 font-serif text-xl italic">{{ __('Votre support') }}</h2>

            @if ($portal['support'])
                <div class="mb-4 rounded-card border border-line bg-bg px-4 py-3">
                    <p class="text-ink">{{ $portal['support']['name'] }}</p>
                    <p class="text-sm text-ink-soft">{{ $portal['support']['size'] }} · {{ $portal['support']['status'] }}</p>
                    @if ($portal['support']['isRejected'])
                        <p class="mt-2 text-sm text-red-600">{{ __("Ce fichier a été refusé par l'analyse antivirus : déposez-en un autre.") }}</p>
                    @elseif ($portal['support']['isPending'])
                        <p class="mt-2 text-sm text-ink-soft">{{ __("Il est en cours de vérification : rien d'autre à faire de votre côté.") }}</p>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ $supportUrl }}" enctype="multipart/form-data">
                @csrf

                <label for="support" class="mb-1.5 block text-sm font-medium text-ink">
                    {{ $portal['support'] ? __('Déposer une nouvelle version') : __('Déposer votre support') }}
                </label>
                <p class="mb-2 text-xs text-ink-soft">
                    {{ __('PDF, présentation ou image, :size Mo au plus. La nouvelle version remplace la précédente.', ['size' => \App\Support\Events\PresentSpeakerPortal::SUPPORT_MAX_SIZE_MB]) }}
                </p>
                <input
                    type="file"
                    id="support"
                    name="support"
                    required
                    accept="{{ collect(\App\Support\Events\PresentSpeakerPortal::SUPPORT_EXTENSIONS)->map(fn (string $extension): string => '.'.$extension)->implode(',') }}"
                    class="mb-4 block w-full text-sm text-ink-soft file:mr-3 file:min-h-9 file:cursor-pointer file:rounded-pill file:border file:border-line file:bg-bg file:px-4 file:py-1.5 file:text-sm file:text-ink"
                >
                @error('support')
                    <p class="mb-4 text-sm text-red-600">{{ $message }}</p>
                @enderror

                <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ __('Envoyer le fichier') }}</button>
            </form>
        </section>

        @if ($portal['event']['briefing'])
            <section>
                <h2 class="mb-3 font-serif text-xl italic">{{ __('Informations pratiques') }}</h2>
                <p class="rounded-card border border-line bg-bg px-4 py-3 text-sm whitespace-pre-line text-ink">{{ $portal['event']['briefing'] }}</p>
            </section>
        @endif

        <p class="mt-10 text-center text-xs text-ink-soft">{{ __('Ce lien est personnel : conservez-le pour revenir sur cette page.') }}</p>
    </div>
@endsection
