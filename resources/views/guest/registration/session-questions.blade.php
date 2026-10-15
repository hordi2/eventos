@extends('guest.layout')

@section('title', __('Questions au conférencier'))

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16">
        <h1 class="mb-1 text-2xl">{{ __('Questions au conférencier') }}</h1>
        <p class="mb-8 text-sm text-ink-soft">
            {{ __('Posez votre question, votez pour celles des autres : les plus soutenues remontent en premier.') }}
        </p>

        <form method="POST" action="{{ $formUrl }}" class="mb-10 rounded-card bg-bg p-5 ring-1 ring-line">
            @csrf
            <textarea
                name="body"
                maxlength="500"
                rows="3"
                class="w-full resize-y rounded-control border border-line bg-transparent px-3 py-2.5 text-base text-ink focus:border-accent focus:ring-0 focus:outline-none"
                placeholder="{{ __('Votre question…') }}"
            >{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-sm text-danger">{{ $message }}</p>
            @enderror
            <button type="submit" class="form-button mt-3 inline-flex min-h-11 items-center rounded-pill px-6 py-2.5">
                {{ __('Envoyer') }}
            </button>
        </form>

        @if ($questions === [])
            <p class="text-sm text-ink-soft">{{ __('Aucune question pour le moment : soyez le premier à en poser une.') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($questions as $question)
                    <li class="rounded-card bg-bg px-5 py-4 ring-1 ring-line">
                        <p class="text-ink">{{ $question['body'] }}</p>

                        @if ($question['status'] === 'answered' && $question['answer'])
                            <p class="mt-2 rounded-control bg-bg-alt px-3 py-2 text-sm text-ink-soft">
                                <span class="font-label text-[0.62rem] tracking-[0.2em] uppercase">{{ __('Réponse') }}</span><br>
                                {{ $question['answer'] }}
                            </p>
                        @endif

                        <form method="POST" action="{{ $question['voteUrl'] }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex min-h-9 items-center gap-2 rounded-pill border px-4 py-1.5 text-sm {{ $question['upvoted'] ? 'border-ink text-ink' : 'border-line text-ink-soft' }}"
                            >
                                &uarr; {{ $question['upvoteCount'] }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
