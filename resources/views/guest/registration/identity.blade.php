@extends('guest.layout')

@section('title', "Inscription — {$event->title}")

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Votre inscription</h1>

        @include('guest.registration._progress', ['step' => 1])

        <form method="POST" action="{{ route('guest.registration.identity.store', [request()->route('organization'), request()->route('event'), $draft->resume_token]) }}" novalidate>
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

            <button type="submit" class="min-h-11 w-full rounded-pill bg-ink px-8 py-3 font-medium text-bg">Continuer</button>
        </form>

        <p class="mt-6 text-center text-xs text-ink-soft">
            Vous pouvez reprendre cette inscription plus tard grâce à ce lien : conservez-le.
        </p>
    </div>
@endsection
