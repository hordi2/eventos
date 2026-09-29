<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Le client de test de Symfony annonce « en-us » par défaut, ce qui
     * ferait basculer les pages invité en anglais (SetGuestLocale). Les
     * tests parlent donc français, sauf ceux qui vérifient justement le
     * choix de la langue et qui envoient leur propre en-tête.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'fr');
    }
}
