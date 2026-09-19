@extends('guest.layout')

@section('title', "Aperçu — {$form->name}")

@section('notice')
    {{-- Aperçu du constructeur : les vrais gabarits du parcours, rien ne s'enregistre. --}}
    <div class="sticky top-0 z-20 bg-ink px-4 py-2 text-center text-xs text-bg">
        Aperçu du formulaire « {{ $form->name }} » : rien n'est enregistré.
        @if (! $form->hasPublishedVersion())
            Ce formulaire n'est pas encore publié : vos invités ne peuvent pas encore y répondre.
        @elseif ($isUnpublished)
            Cette version n'est pas encore publiée : vos invités voient encore la précédente.
        @endif
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-lg px-4 py-10 sm:py-16">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-8 text-2xl">Vos réponses</h1>

        @include('guest.registration._progress', ['step' => 2])

        <form onsubmit="return false" novalidate aria-label="Aperçu du formulaire">
            <fieldset disabled class="mb-8 space-y-4 rounded-card bg-bg p-4 ring-1 ring-line">
                <legend class="sr-only">Coordonnées</legend>
                <p class="text-sm text-ink-soft">Étape précédente : l'invité donne son adresse e-mail, son nom et son téléphone.</p>
            </fieldset>

            @forelse ($fields as $field)
                @include('guest.registration._field', ['field' => $field, 'value' => null, 'donorDefaultName' => ''])
            @empty
                <p class="mb-8 text-ink-soft">Ce formulaire ne pose encore aucune question.</p>
            @endforelse

            <button type="button" disabled class="form-button min-h-11 w-full rounded-pill px-8 py-3 font-medium opacity-80">Continuer</button>
        </form>
    </div>
@endsection
