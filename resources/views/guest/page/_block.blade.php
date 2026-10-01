{{-- Un bloc de la page publique (PageBlocks). Le titre est facultatif :
     sans lui, le bloc garde son titre d'origine quand il en a un. --}}
@php($blockTitle = $block['title'] ?? null)

@switch($block['type'])
    @case('text')
        <section class="mb-10">
            @if ($blockTitle)
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle }}</h2>
            @endif
            <p class="whitespace-pre-line text-ink">{{ $block['body'] ?? '' }}</p>
        </section>
        @break

    @case('image')
        @if (! empty($block['path']))
            <figure class="mb-10">
                <img
                    src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($block['path']) }}"
                    alt="{{ $block['alt'] ?? '' }}"
                    loading="lazy"
                    class="w-full rounded-card"
                >
                @if ($blockTitle)
                    <figcaption class="mt-2 text-sm text-ink-soft">{{ $blockTitle }}</figcaption>
                @endif
            </figure>
        @endif
        @break

    @case('video')
        @php($video = \App\Domain\Form\Support\VideoEmbed::from($block['url'] ?? null))
        @if ($video !== null)
            <section class="mb-10">
                @if ($blockTitle)
                    <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle }}</h2>
                @endif
                {{-- Le lecteur n'arrive qu'au clic : la page reste légère. --}}
                <div x-data="{ playing: false }" class="overflow-hidden rounded-card border border-line">
                    <template x-if="playing">
                        <div class="aspect-video w-full">
                            <iframe
                                src="{{ $video->embedUrl }}"
                                title="{{ $blockTitle ?? $page->title }}"
                                loading="lazy"
                                allow="accelerometer; autoplay; encrypted-media; picture-in-picture"
                                allowfullscreen
                                class="h-full w-full"
                            ></iframe>
                        </div>
                    </template>
                    <button type="button" x-show="! playing" @click="playing = true" class="flex min-h-11 w-full items-center gap-3 px-4 py-3 text-left">
                        <span aria-hidden="true" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-ink text-bg">▶</span>
                        <span>
                            <span class="block text-sm font-medium text-ink">{{ __('Lire la vidéo') }}</span>
                            <span class="block text-xs text-ink-soft">{{ $video->provider }} · {{ __('se charge seulement quand vous cliquez') }}</span>
                        </span>
                    </button>
                </div>
            </section>
        @endif
        @break

    @case('program')
        @if (($block['items'] ?? []) !== [])
            {{-- Le programme comme une affiche : chaque moment numéroté,
                 son heure au-dessus de son titre. --}}
            <section class="mb-14 text-center">
                <h2 class="mb-8 font-serif text-2xl italic">{{ $blockTitle ?? __('Programme') }}</h2>
                <ol class="mx-auto max-w-[30rem] space-y-8">
                    @foreach ($block['items'] as $index => $item)
                        <li>
                            <p class="font-label text-[0.6rem] tracking-[0.25em] text-ink-soft">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</p>
                            @if (! empty($item['time']))
                                <p class="mt-2 font-serif text-2xl text-ink">{{ $item['time'] }}</p>
                            @endif
                            <p class="mt-1 font-label text-xs tracking-[0.2em] text-ink uppercase">{{ $item['title'] ?? '' }}</p>
                            @if (! empty($item['description']))
                                <p class="mt-2 text-sm text-ink-soft">{{ $item['description'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
        @break

    @case('faq')
        @if (($block['items'] ?? []) !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Questions fréquentes') }}</h2>
                <div class="space-y-2">
                    @foreach ($block['items'] as $item)
                        <div x-data="{ open: false }" class="rounded-card bg-bg ring-1 ring-line">
                            <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between px-4 py-3 text-left font-medium">
                                {{ $item['question'] ?? '' }}
                                <span x-text="open ? '−' : '+'"></span>
                            </button>
                            <p x-show="open" x-cloak class="px-4 pb-3 text-sm text-ink-soft">{{ $item['answer'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        @break

    @case('venue')
        @if (! $page->isOnline && $page->venueName !== null)
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Lieu') }}</h2>
                <p class="mb-3">{{ $page->venueName }}</p>
                @if ($page->venueAddress !== null)
                    <p class="mb-3 text-sm text-ink-soft">{{ $page->venueAddress }}</p>
                @endif
                @if ($page->venueLatitude !== null && $page->venueLongitude !== null)
                    <iframe
                        class="h-64 w-full rounded-card"
                        loading="lazy"
                        src="https://www.openstreetmap.org/export/embed.html?bbox={{ $page->venueLongitude - 0.01 }}%2C{{ $page->venueLatitude - 0.01 }}%2C{{ $page->venueLongitude + 0.01 }}%2C{{ $page->venueLatitude + 0.01 }}&marker={{ $page->venueLatitude }}%2C{{ $page->venueLongitude }}"
                        title="{{ __('Carte du lieu') }}"
                    ></iframe>
                @endif
            </section>
        @endif
        @break

    @case('speakers')
        @if ($page->speakers !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Intervenants') }}</h2>
                <ul class="grid gap-4 sm:grid-cols-2">
                    @foreach ($page->speakers as $speaker)
                        <li class="rounded-card bg-bg p-4 ring-1 ring-line">
                            <div class="mb-2 flex items-center gap-3">
                                @if ($speaker['photoUrl'])
                                    <img src="{{ $speaker['photoUrl'] }}" alt="" loading="lazy" class="h-14 w-14 rounded-full object-cover">
                                @endif
                                <div class="min-w-0">
                                    <p class="font-medium text-ink">{{ $speaker['name'] }}</p>
                                    <p class="text-sm text-ink-soft">{{ collect([$speaker['role'], $speaker['company']])->filter()->implode(' · ') }}</p>
                                </div>
                            </div>
                            @if ($speaker['bio'])
                                <p class="text-sm whitespace-pre-line text-ink-soft">{{ $speaker['bio'] }}</p>
                            @endif
                            @if ($speaker['sessions'] !== [])
                                <p class="mt-2 text-xs text-ink-soft">{{ __('Intervient dans :sessions', ['sessions' => implode(', ', $speaker['sessions'])]) }}</p>
                            @endif
                            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                                @if ($speaker['websiteUrl'])
                                    <a href="{{ $speaker['websiteUrl'] }}" target="_blank" rel="noopener nofollow" class="text-accent underline">{{ __('Site web') }}</a>
                                @endif
                                @if ($speaker['linkedinUrl'])
                                    <a href="{{ $speaker['linkedinUrl'] }}" target="_blank" rel="noopener nofollow" class="text-accent underline">LinkedIn</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        @break

    @case('sessions')
        @if ($page->sessions !== [])
            <section class="mb-10">
                <h2 class="mb-3 font-serif text-xl italic">{{ $blockTitle ?? __('Programme') }}</h2>
                @php($days = collect($page->sessions)->groupBy('day'))
                @foreach ($days as $day => $daySessions)
                    @if ($days->count() > 1)
                        <h3 class="mt-4 mb-2 font-medium text-ink">{{ $day }}</h3>
                    @endif
                    <ul class="space-y-3">
                        @foreach ($daySessions as $session)
                            <li class="flex gap-4">
                                <span class="w-28 shrink-0 text-sm font-medium text-ink">{{ $session['time'] }}</span>
                                <div>
                                    <p class="font-medium text-ink">{{ $session['title'] }}</p>
                                    @if ($session['room'])
                                        <p class="text-sm text-ink-soft">{{ $session['room'] }}</p>
                                    @endif
                                    @if ($session['speakers'] !== [])
                                        <p class="text-sm text-ink-soft">{{ __('Avec :speakers', ['speakers' => implode(', ', $session['speakers'])]) }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </section>
        @endif
        @break

    @case('countdown')
        {{-- Compte à rebours : calculé chez l'invité, à partir de l'instant
             de début envoyé en UTC — jamais l'heure du serveur. Quatre
             cases, comme sur un faire-part : le chiffre se lit de loin,
             l'unité se lit de près. --}}
        <section
            class="mb-14 text-center"
            x-data="{
                parts: { days: '00', hours: '00', minutes: '00', seconds: '00' },
                started: false,
                tick() {
                    const total = Math.max(0, Math.floor((new Date('{{ $event->start_at->toIso8601String() }}') - new Date()) / 1000));
                    this.started = total === 0;
                    const pad = (value) => String(value).padStart(2, '0');
                    this.parts = {
                        days: pad(Math.floor(total / 86400)),
                        hours: pad(Math.floor((total % 86400) / 3600)),
                        minutes: pad(Math.floor((total % 3600) / 60)),
                        seconds: pad(total % 60),
                    };
                },
            }"
            x-init="tick(); setInterval(() => tick(), 1000)"
        >
            <h2 class="mb-6 font-label text-[0.68rem] tracking-[0.25em] text-ink-soft uppercase">
                {{ $blockTitle ?? __('Le grand jour approche') }}
            </h2>

            <p x-show="started" x-cloak class="font-serif text-3xl italic">{{ __("C'est aujourd'hui !") }}</p>

            <div x-show="! started" class="mx-auto grid max-w-[26rem] grid-cols-4 gap-3">
                @foreach ([['days', __('Jours')], ['hours', __('Heures')], ['minutes', __('Minutes')], ['seconds', __('Secondes')]] as [$key, $unit])
                    <div class="rounded-card border border-line bg-bg px-2 py-4">
                        <p class="font-serif text-2xl tabular-nums text-ink sm:text-3xl" x-text="parts.{{ $key }}">00</p>
                        <p class="mt-1 font-label text-[0.6rem] tracking-[0.15em] text-ink-soft uppercase">{{ $unit }}</p>
                    </div>
                @endforeach
            </div>
        </section>
        @break
    @case('guest_book')
        {{-- Livre d'or : les mots déjà laissés, puis de quoi en écrire un.
             Publié aussitôt, masquable par l'organisateur. --}}
        <section class="mb-14 text-center">
            <h2 class="mb-8 font-serif text-2xl italic">{{ $blockTitle ?? __("Livre d'or") }}</h2>

            @if (session('status') === 'guest-book-signed')
                <p role="status" class="mx-auto mb-8 max-w-[30rem] rounded-card bg-bg px-4 py-3 text-sm text-ink ring-1 ring-line">
                    {{ __('Merci, votre mot est enregistré.') }}
                </p>
            @endif

            @if ($page->guestBookMessages !== [])
                <ul class="mx-auto mb-10 max-w-[30rem] space-y-6 text-left">
                    @foreach ($page->guestBookMessages as $entry)
                        <li class="rounded-card border border-line bg-bg px-5 py-4">
                            <p class="whitespace-pre-line text-ink italic">« {{ $entry['message'] }} »</p>
                            <p class="mt-3 font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">
                                {{ $entry['author'] }} · {{ $entry['writtenAt'] }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route('guest.registration.guest-book', [request()->route('organization'), request()->route('event')]) }}" class="mx-auto max-w-[30rem] text-left">
                @csrf

                <div class="mb-4">
                    <label for="guest_book_author" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Votre nom') }}</label>
                    <input type="text" id="guest_book_author" name="author_name" value="{{ old('author_name') }}" required maxlength="120" class="w-full rounded-control border border-line px-3 py-2 text-ink">
                    @error('author_name')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="guest_book_message" class="mb-1.5 block text-sm font-medium text-ink">{{ __('Votre message') }}</label>
                    <textarea id="guest_book_message" name="message" rows="4" required maxlength="1000" class="w-full rounded-control border border-line px-3 py-2 text-ink">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium">{{ __('Laisser mon message') }}</button>
            </form>
        </section>
        @break

@endswitch
