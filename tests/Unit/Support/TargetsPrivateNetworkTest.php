<?php

declare(strict_types=1);

use App\Support\Webhooks\TargetsPrivateNetwork;

it('refuse une adresse IP littérale privée, loopback ou lien-local', function (string $url): void {
    expect(app(TargetsPrivateNetwork::class)->handle($url))->toBeTrue();
})->with([
    'loopback IPv4' => ['https://127.0.0.1/hook'],
    'loopback IPv6' => ['https://[::1]/hook'],
    'privé RFC 1918' => ['https://10.0.0.5/hook'],
    'privé RFC 1918 (172.16/12)' => ['https://172.16.0.1/hook'],
    'privé RFC 1918 (192.168/16)' => ['https://192.168.1.1/hook'],
    'lien-local, métadonnées cloud' => ['https://169.254.169.254/latest/meta-data'],
]);

it('refuse localhost et les domaines internes évidents', function (string $url): void {
    expect(app(TargetsPrivateNetwork::class)->handle($url))->toBeTrue();
})->with([
    'localhost' => ['https://localhost/hook'],
    'sous-domaine .localhost' => ['https://app.localhost/hook'],
    '.local' => ['https://backend.local/hook'],
]);

it('accepte une adresse publique', function (): void {
    expect(app(TargetsPrivateNetwork::class)->handle('https://93.184.216.34/hook'))->toBeFalse();
});

it('accepte un domaine public réel, résolu par DNS', function (): void {
    expect(app(TargetsPrivateNetwork::class)->handle('https://example.com/hook'))->toBeFalse();
});
