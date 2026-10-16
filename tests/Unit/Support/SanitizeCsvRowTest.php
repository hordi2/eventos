<?php

declare(strict_types=1);

use App\Support\Export\SanitizeCsvRow;

it('préfixe d\'une apostrophe toute valeur commençant par =, +, -, @, une tabulation ou un retour chariot', function (string $dangerous): void {
    expect(SanitizeCsvRow::handle([$dangerous]))->toBe(["'{$dangerous}"]);
})->with([
    '=HYPERLINK("http://evil.test","clic")',
    '+1234',
    '-1234',
    '@SUM(1+1)',
    "\tcellule décalée",
    "\rcellule décalée",
]);

it('laisse intactes les valeurs sans préfixe dangereux', function (): void {
    expect(SanitizeCsvRow::handle(['Grace Mbuyi', 'grace@example.org']))
        ->toBe(['Grace Mbuyi', 'grace@example.org']);
});

it('laisse passer les valeurs non-chaînes et les chaînes vides sans y toucher', function (): void {
    expect(SanitizeCsvRow::handle([42, null, '', 12.5]))->toBe([42, null, '', 12.5]);
});
