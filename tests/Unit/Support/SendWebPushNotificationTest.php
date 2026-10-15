<?php

declare(strict_types=1);

use App\Support\WebPush\SendWebPushNotification;
use App\Support\WebPush\VapidKeyPair;
use Illuminate\Support\Facades\Http;

function testWebPushVapidKeyPair(): VapidKeyPair
{
    return new VapidKeyPair(
        'BMkIIUURS64tQfqONAJ-xW0BeMBGxj2DCg0sTV2OTsDtoYbVczslcYKpD06v14DNLOc9aDCKs3mv-jEG06Ye7Bg',
        base64_decode('LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JR0hBZ0VBTUJNR0J5cUdTTTQ5QWdFR0NDcUdTTTQ5QXdFSEJHMHdhd0lCQVFRZ3FQRHJFOUlEbDhmd29QeFcKMi9SVEs2WS9DTlFXKzNvdkxOZGMwVWJTbzE2aFJBTkNBQVRKQ0NGRkVVdXVMVUg2ampRQ2ZzVnRBWGpBUnNZOQpnd29OTEUxZGprN0E3YUdHMVhNN0pYR0NxUTlPcjllQXpTem5QV2d3aXJONXIvb3hCdE9tSHV3WQotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg==', strict: true),
        'mailto:contact@itaza.app',
    );
}

it('retourne vrai quand le service de push accepte la notification', function (): void {
    Http::fake(['https://push.example.com/*' => Http::response('', 201)]);

    $accepted = (new SendWebPushNotification(testWebPushVapidKeyPair()))->handle('https://push.example.com/abc');

    expect($accepted)->toBeTrue();
});

it('retourne faux quand l\'abonnement a expiré (404 ou 410)', function (): void {
    Http::fake(['https://push.example.com/*' => Http::response('', 410)]);
    expect((new SendWebPushNotification(testWebPushVapidKeyPair()))->handle('https://push.example.com/abc'))->toBeFalse();

    Http::fake(['https://push.example.com/*' => Http::response('', 404)]);
    expect((new SendWebPushNotification(testWebPushVapidKeyPair()))->handle('https://push.example.com/abc'))->toBeFalse();
});

it('lève une exception pour tout autre échec', function (): void {
    Http::fake(['https://push.example.com/*' => Http::response('erreur', 500)]);

    expect(fn () => (new SendWebPushNotification(testWebPushVapidKeyPair()))->handle('https://push.example.com/abc'))
        ->toThrow(RuntimeException::class);
});

it('signe un jeton VAPID dont l\'audience est l\'origine de l\'endpoint', function (): void {
    Http::fake(['https://push.example.com/*' => Http::response('', 201)]);

    (new SendWebPushNotification(testWebPushVapidKeyPair()))->handle('https://push.example.com/abc?x=1');

    Http::assertSent(function ($request): bool {
        $authorization = (string) $request->header('Authorization')[0];
        [, $jwt] = explode('t=', explode(',', $authorization)[0]);
        $payload = json_decode(base64_decode(explode('.', $jwt)[1]), true);

        return $payload['aud'] === 'https://push.example.com';
    });
});

it('n\'est pas configuré quand il manque un seul des trois réglages', function (): void {
    config(['webpush.public_key' => null, 'webpush.private_key' => null, 'webpush.subject' => null]);
    expect(VapidKeyPair::isConfigured())->toBeFalse();

    config(['webpush.public_key' => 'une-clé', 'webpush.private_key' => 'une-clé-privée', 'webpush.subject' => null]);
    expect(VapidKeyPair::isConfigured())->toBeFalse();
});
