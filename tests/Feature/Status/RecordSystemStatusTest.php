<?php

declare(strict_types=1);

use App\Mail\StatusAlertMail;
use App\Support\Status\Models\SystemStatusCheck;
use App\Support\Status\RecordSystemStatus;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config(['services.status.alert_email' => 'ops@example.com']);
});

it('enregistre une vérification sans alerter au tout premier passage sain', function (): void {
    Mail::fake();

    app(RecordSystemStatus::class)->handle();

    expect(SystemStatusCheck::query()->count())->toBe(1);
    Mail::assertNothingQueued();
});

it('n\'alerte pas quand l\'état ne change pas', function (): void {
    SystemStatusCheck::query()->create([
        'checked_at' => now()->subMinutes(5),
        'is_healthy' => true,
        'components' => ['database' => true, 'redis' => true],
    ]);
    Mail::fake();

    app(RecordSystemStatus::class)->handle();

    Mail::assertNothingQueued();
});

it('alerte STATUS_ALERT_EMAIL quand l\'état passe d\'en échec à opérationnel', function (): void {
    SystemStatusCheck::query()->create([
        'checked_at' => now()->subMinutes(5),
        'is_healthy' => false,
        'components' => ['database' => false, 'redis' => true],
    ]);
    Mail::fake();

    app(RecordSystemStatus::class)->handle();

    Mail::assertQueued(StatusAlertMail::class, fn (StatusAlertMail $mail): bool => $mail->hasTo('ops@example.com'));
});

it('n\'alerte personne quand STATUS_ALERT_EMAIL est vide', function (): void {
    config(['services.status.alert_email' => '']);
    SystemStatusCheck::query()->create([
        'checked_at' => now()->subMinutes(5),
        'is_healthy' => false,
        'components' => ['database' => false, 'redis' => true],
    ]);
    Mail::fake();

    app(RecordSystemStatus::class)->handle();

    Mail::assertNothingQueued();
});
