<?php

declare(strict_types=1);

use App\Support\Status\CheckSystemHealth;

it('rapporte la base de données et Redis comme opérationnels quand ils répondent', function (): void {
    $result = app(CheckSystemHealth::class)->handle();

    expect($result->isHealthy())->toBeTrue();
    expect($result->components)->toBe(['database' => true, 'redis' => true]);
});
