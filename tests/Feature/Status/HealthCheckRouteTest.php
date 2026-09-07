<?php

declare(strict_types=1);

it('répond 200 sur /up quand la base de données et Redis sont opérationnels', function (): void {
    $this->get('/up')->assertOk();
});
