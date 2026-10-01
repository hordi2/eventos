@php
    $steps = [__('Identité'), __('Réponses'), __('Récapitulatif')];
@endphp

<nav aria-label="{{ __("Étapes de l'inscription") }}" class="itaza-progress mb-8">
    <ol class="flex items-center gap-2">
        @foreach ($steps as $index => $label)
            @php($number = $index + 1)
            <li class="flex flex-1 items-center gap-2">
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-medium
                        {{ $number <= $step ? 'bg-ink text-bg' : 'border border-line text-ink-soft' }}"
                    aria-current="{{ $number === $step ? 'step' : 'false' }}"
                >{{ $number }}</span>
                <span class="hidden font-label text-[0.62rem] tracking-[0.18em] uppercase sm:inline {{ $number === $step ? 'text-ink' : 'text-ink-soft' }}">{{ $label }}</span>
                @if (! $loop->last)
                    <span class="h-px flex-1 {{ $number < $step ? 'bg-ink' : 'bg-line' }}" aria-hidden="true"></span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
