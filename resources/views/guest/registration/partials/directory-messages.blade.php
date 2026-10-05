{{-- Messagerie interne (D8) : une conversation par interlocuteur. Rien ne
     part par e-mail, tout se lit ici. --}}
@if ($event->has_attendee_messaging && $conversations !== [])
    <h2 class="mb-3 font-serif text-xl italic">{{ __('Mes messages') }}</h2>
    <ul class="mb-10 space-y-3">
        @foreach ($conversations as $conversation)
            <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="mb-3 text-ink">{{ $conversation['name'] }}</p>

                <ul class="mb-3 space-y-2">
                    @foreach ($conversation['messages'] as $message)
                        <li class="{{ $message['mine'] ? 'text-right' : '' }}">
                            <span class="inline-block max-w-[85%] rounded-card px-4 py-2 text-sm {{ $message['mine'] ? 'bg-ink text-bg' : 'bg-bg-alt text-ink' }}">
                                {{ $message['body'] }}
                            </span>
                            <span class="mt-1 block text-[0.65rem] text-ink-soft">{{ $message['sentAt'] }}</span>
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ $actionUrls['message'] }}" class="space-y-2">
                    @csrf
                    <input type="hidden" name="recipient_registration_id" value="{{ $conversation['id'] }}">
                    <textarea name="body" rows="2" maxlength="2000" required placeholder="{{ __('Répondre…') }}" class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink"></textarea>
                    <button type="submit" class="form-button inline-flex min-h-10 items-center rounded-pill px-5 py-2">
                        {{ __('Envoyer') }}
                    </button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
