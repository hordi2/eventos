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
                                {{ $message['body'] ?? __('Message retiré par l’organisateur.') }}
                            </span>
                            <span class="mt-1 block text-[0.65rem] text-ink-soft">{{ $message['sentAt'] }}</span>

                            @if (! $message['mine'] && $message['body'] !== null)
                                {{-- Signaler ce qu'on a reçu : l'organisateur ne
                                     verra que ce message, pas la conversation. --}}
                                <details class="mt-1">
                                    <summary class="cursor-pointer text-[0.65rem] text-ink-soft">{{ __('Signaler ce message') }}</summary>
                                    <form method="POST" action="{{ $actionUrls['report'] }}" class="mt-2 space-y-2 text-left">
                                        @csrf
                                        <input type="hidden" name="message_id" value="{{ $message['id'] }}">
                                        <input type="text" name="reason" maxlength="500" placeholder="{{ __('Pourquoi ? (optionnel)') }}" class="w-full rounded-control border border-line bg-bg px-3 py-2 text-sm text-ink">
                                        <button type="submit" class="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink">
                                            {{ __('Envoyer le signalement') }}
                                        </button>
                                    </form>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ $actionUrls['block'] }}" class="mb-3">
                    @csrf
                    <input type="hidden" name="registration_id" value="{{ $conversation['id'] }}">
                    <button type="submit" class="text-sm text-danger underline hover:no-underline">
                        {{ __('Bloquer cette personne') }}
                    </button>
                </form>

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

{{-- Personnes bloquées : on peut toujours revenir sur sa décision. --}}
@if ($blocked !== [])
    <h2 class="mb-3 font-serif text-xl italic">{{ __('Personnes bloquées') }}</h2>
    <ul class="mb-10 space-y-2">
        @foreach ($blocked as $person)
            <li class="flex items-center justify-between gap-3 rounded-card bg-bg px-5 py-3 ring-1 ring-line">
                <span class="text-ink">{{ $person['name'] }}</span>
                <form method="POST" action="{{ $actionUrls['block'] }}">
                    @csrf
                    <input type="hidden" name="registration_id" value="{{ $person['id'] }}">
                    <input type="hidden" name="unblock" value="1">
                    <button type="submit" class="text-sm text-accent underline hover:no-underline">{{ __('Débloquer') }}</button>
                </form>
            </li>
        @endforeach
    </ul>
@endif
