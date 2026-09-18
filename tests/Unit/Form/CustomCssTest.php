<?php

declare(strict_types=1);

use App\Domain\Form\Support\CustomCss;

it('accepte une feuille ordinaire et en retire les commentaires', function (): void {
    $css = ".carte { border-radius: 2rem; }\n/* note de l'organisateur */\n.bouton { letter-spacing: .04em; }";

    expect(CustomCss::problems($css))->toBe([]);
    expect(CustomCss::forDelivery($css))->toContain('border-radius: 2rem');
    expect(CustomCss::forDelivery($css))->not->toContain('note de l\'organisateur');
});

it('accepte une image de la bibliothèque et refuse une adresse extérieure', function (): void {
    expect(CustomCss::problems(".entete { background-image: url('/storage/organization-images/1/a.jpg'); }"))->toBe([]);
    expect(CustomCss::problems('.entete { background-image: url(https://pisteur.test/p.gif); }'))->toHaveCount(1);
});

it('refuse ce qui ferait sortir la feuille de son rôle', function (string $css): void {
    expect(CustomCss::problems($css))->not->toBe([]);
    // Refusée à l'enregistrement, et jamais livrée même si elle est en base.
    expect(CustomCss::forDelivery($css))->toBe('');
})->with([
    ['@import url("https://exemple.test/tout.css");'],
    ['.x { width: expression(alert(1)); }'],
    ['.x { behavior: url(/xss.htc); }'],
    ['.x { -moz-binding: url(/xss.xml#x); }'],
    ['</style><script>alert(1)</script>'],
    ['/* @import */ @import "autre.css";'],
]);

it('ne livre rien d\'une feuille vide ou trop longue', function (): void {
    expect(CustomCss::forDelivery(null))->toBe('');
    expect(CustomCss::forDelivery("   \n  "))->toBe('');
    expect(CustomCss::forDelivery('.x{}'.str_repeat('/* '.str_repeat('a', 100).' */', 60)))->toBe('');
});
