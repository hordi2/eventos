@extends('guest.layout')

@section('title', 'Soutien — ' . config('app.name', 'Itaza Invitation'))

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-16">
        <h1 class="mb-4 text-3xl">Besoin d'aide ?</h1>

        <p class="mb-6 text-ink-soft">
            Si vous êtes organisateur, le guide de démarrage, les tutoriels des parcours principaux et la FAQ sont
            disponibles depuis votre tableau de bord, dans <strong>Paramètres → Aide</strong>.
        </p>

        <p class="text-ink-soft">
            Vous ne trouvez pas de réponse, ou vous rencontrez un problème technique ? Écrivez-nous à
            <a href="mailto:{{ config('mail.from.address') }}" class="underline underline-offset-2">{{ config('mail.from.address') }}</a>
            en décrivant votre situation le plus précisément possible — nous vous répondons dans les meilleurs délais.
        </p>
    </div>
@endsection
