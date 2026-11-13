<?php

declare(strict_types=1);

it('relie le pied de page public aux comptes Instagram et Facebook d\'Itaza', function (): void {
    $this->get('/terms')
        ->assertOk()
        ->assertSee('href="https://www.instagram.com/itazainvitation/"', false)
        ->assertSee('href="https://www.facebook.com/share/1EEdj2y4hX/"', false)
        ->assertDontSee('bientôt disponible');
});
