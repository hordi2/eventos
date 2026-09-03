<?php

declare(strict_types=1);

arch('les fichiers du domaine métier déclarent strict_types')
    ->expect('App\Domain')
    ->toUseStrictTypes();

arch('les contrôleurs ne contiennent aucun appel de debug')
    ->expect('App\Http\Controllers')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('aucune classe métier n\'utilise directement env()')
    ->expect('App\Domain')
    ->not->toUse('env');

// Section 3 du CLAUDE.md : un module de Domain/ ne dépend jamais directement
// des modèles d'un autre module (Form référence Event uniquement par
// event_id, jamais par une relation Eloquent — voir T-020).
arch('Domain/Form ne dépend pas des modèles de Domain/Event')
    ->expect('App\Domain\Form')
    ->not->toUse('App\Domain\Event\Models');

// Même règle pour Contact (T-040) : l'historique de participation d'une
// fiche contact traverse Registration (Domain/Form) au niveau du
// contrôleur, jamais via une relation Eloquent portée par le modèle.
arch('Domain/Contact ne dépend pas des modèles de Domain/Form')
    ->expect('App\Domain\Contact')
    ->not->toUse('App\Domain\Form\Models');

// Même règle pour Messaging (T-043) : qui a le droit de recevoir un e-mail
// (Contact) est décidé par App\Support\Messaging\SendEmailToContact, jamais
// par Domain/Messaging lui-même.
arch('Domain/Messaging ne dépend pas des modèles de Domain/Contact')
    ->expect('App\Domain\Messaging')
    ->not->toUse('App\Domain\Contact\Models');
