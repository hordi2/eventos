<?php

declare(strict_types=1);

use App\Domain\Messaging\Models\EmailMessage;
use App\Domain\Organization\Models\Organization;
use App\Jobs\SendEmailMessageJob;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Chaque test Feature démarre avec une application fraîche (nouvelle
 * exécution de AppServiceProvider::boot()) : remplacer le limiteur ici ne
 * fuit jamais vers les autres tests.
 */
it('respecte le débit configuré : au-delà de la limite, le job est repoussé sans être traité', function (): void {
    RateLimiter::for('email-sends', fn (): Limit => Limit::perMinute(2));
    Mail::fake();
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $messages = EmailMessage::factory()->for($organization)->count(3)->create();

    foreach ($messages as $message) {
        SendEmailMessageJob::dispatch($message->id, $organization->id, '<p>Corps</p>', null);
    }

    $statuses = $messages->map(fn (EmailMessage $m): string => $m->fresh()->status->value);

    expect($statuses->filter(fn (string $s): bool => $s === 'sent'))->toHaveCount(2);
    expect($statuses->filter(fn (string $s): bool => $s === 'queued'))->toHaveCount(1);
});
