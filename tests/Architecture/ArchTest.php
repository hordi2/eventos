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
