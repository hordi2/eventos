<?php

declare(strict_types=1);

namespace App\Support\Webhooks;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class SafeWebhookUrlRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (app(TargetsPrivateNetwork::class)->handle($value)) {
            $fail("Cette adresse pointe vers un réseau interne : elle n'est pas acceptée comme destination de webhook.");
        }
    }
}
