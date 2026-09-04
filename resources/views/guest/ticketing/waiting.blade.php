@extends('guest.layout')

@section('title', "Paiement en cours — {$event->title}")

@php
    // Recharge cette page toutes les 5 secondes : status() ré-évalue l'état
    // réel de la commande et redirige dès qu'il est connu (webhook Mobile
    // Money reçu). Léger et fonctionne sans JavaScript, contrairement à un
    // polling en fetch() (contrainte de poids des pages invité, §2 CLAUDE.md).
@endphp
<meta http-equiv="refresh" content="5">

@section('content')
    <div class="mx-auto max-w-lg px-4 py-16 text-center">
        <p class="mb-1 text-sm font-medium text-ink-soft">{{ $event->title }}</p>
        <h1 class="mb-4 text-2xl">Paiement en cours de confirmation</h1>
        <p class="text-ink-soft">
            Vérifiez votre téléphone et validez la demande de paiement Mobile Money. Cette page se met à jour
            automatiquement — vous pouvez patienter, la confirmation peut prendre jusqu'à 5 minutes.
        </p>
    </div>
@endsection
