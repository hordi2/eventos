{{-- Rendez-vous entre participants (D8) : ceux qu'on a proposés et ceux
     qu'on a reçus, du plus proche au plus lointain. --}}
@if ($meetings !== [])
    <h2 class="mb-3 font-serif text-xl italic">{{ __('Mes rendez-vous') }}</h2>
    <ul class="mb-10 space-y-3">
        @foreach ($meetings as $meeting)
            <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                <p class="text-ink">{{ $meeting['name'] }}</p>
                <p class="mt-1 text-sm text-ink-soft">
                    {{ $meeting['when'] }}@if ($meeting['place']) &middot; {{ $meeting['place'] }}@endif
                </p>
                @if ($meeting['message'])
                    <p class="mt-2 text-sm text-ink-soft">« {{ $meeting['message'] }} »</p>
                @endif
                <p class="mt-2 font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{{ $meeting['statusLabel'] }}</p>

                @if ($meeting['pending'])
                    <form method="POST" action="{{ $meeting['answerUrl'] }}" class="mt-3 flex flex-wrap gap-2">
                        @csrf
                        @if ($meeting['mine'])
                            <button type="submit" name="status" value="cancelled" class="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink">
                                {{ __('Annuler') }}
                            </button>
                        @else
                            <button type="submit" name="status" value="accepted" class="form-button inline-flex min-h-9 items-center rounded-pill px-4 py-1.5">
                                {{ __('J’accepte') }}
                            </button>
                            <button type="submit" name="status" value="declined" class="inline-flex min-h-9 items-center rounded-pill border border-line px-4 py-1.5 text-sm text-ink">
                                {{ __('Je décline') }}
                            </button>
                        @endif
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
@endif
