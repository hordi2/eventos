{{-- Suggestions de mise en relation (D8) : ceux qui partagent au moins un
     centre d'intérêt, et qu'on n'a pas encore rencontrés. --}}
@if ($suggestions !== [])
    <h2 class="mb-3 font-serif text-xl italic">{{ __('À rencontrer') }}</h2>
    <ul class="mb-10 space-y-3">
        @foreach ($suggestions as $suggestion)
            <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="text-ink">{{ $suggestion['name'] }}</p>
                @if ($suggestion['headline'])
                    <p class="mt-1 text-sm text-ink-soft">{{ $suggestion['headline'] }}</p>
                @endif
                <p class="mt-2 text-xs text-ink-soft">
                    {{ __('En commun : :interests', ['interests' => implode(', ', $suggestion['shared'])]) }}
                </p>

                <details class="mt-3">
                    <summary class="cursor-pointer text-sm text-accent">{{ __('Proposer un rendez-vous') }}</summary>
                    <form method="POST" action="{{ $actionUrls['meeting'] }}" class="mt-3 space-y-2">
                        @csrf
                        <input type="hidden" name="guest_registration_id" value="{{ $suggestion['id'] }}">
                        <input type="datetime-local" name="starts_at" required class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink">
                        <input type="text" name="place" maxlength="120" placeholder="{{ __('Où ? (optionnel)') }}" class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink">
                        <input type="text" name="message" maxlength="500" placeholder="{{ __('Un mot (optionnel)') }}" class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink">
                        <button type="submit" class="form-button inline-flex min-h-10 items-center rounded-pill px-5 py-2">
                            {{ __('Proposer') }}
                        </button>
                    </form>
                </details>

                @if ($event->has_attendee_messaging)
                    <details class="mt-2">
                        <summary class="cursor-pointer text-sm text-accent">{{ __('Écrire un message') }}</summary>
                        <form method="POST" action="{{ $actionUrls['message'] }}" class="mt-3 space-y-2">
                            @csrf
                            <input type="hidden" name="recipient_registration_id" value="{{ $suggestion['id'] }}">
                            <textarea name="body" rows="3" maxlength="2000" required class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink"></textarea>
                            <button type="submit" class="form-button inline-flex min-h-10 items-center rounded-pill px-5 py-2">
                                {{ __('Envoyer') }}
                            </button>
                        </form>
                    </details>
                @endif
            </li>
        @endforeach
    </ul>
@endif
