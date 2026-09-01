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
