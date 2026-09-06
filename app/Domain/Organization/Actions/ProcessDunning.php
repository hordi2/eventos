<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\PaymentFailedMail;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Mail;

/**
 * Relances d'échec de prélèvement (T-074, AC : « relances J+1, J+3, J+7,
 * puis restriction »). `dunning_stage` avance d'un cran par relance
 * envoyée, jamais en arrière ici : seul le webhook
 * invoice.payment_succeeded (RecordStripeBillingWebhookEvent) le remet à
 * zéro, en confirmation d'un paiement réellement régularisé.
 */
final class ProcessDunning
{
    /**
     * @var array<int, int>
     */
    private const STAGE_DAYS = [1 => 1, 2 => 3, 3 => 7];

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(): void
    {
        $organizations = Organization::query()
            ->withoutGlobalScopes()
            ->where('subscription_status', 'past_due')
            ->whereNotNull('payment_failed_at')
            ->where('dunning_stage', '<', 3)
            ->get();

        foreach ($organizations as $organization) {
            $this->processOne($organization);
        }
    }

    private function processOne(Organization $organization): void
    {
        $this->currentOrganization->set($organization);

        $daysSinceFailure = (int) $organization->payment_failed_at->diffInDays(now());
        $nextStage = $organization->dunning_stage + 1;

        if (! array_key_exists($nextStage, self::STAGE_DAYS) || $daysSinceFailure < self::STAGE_DAYS[$nextStage]) {
            return;
        }

        $organization->update(['dunning_stage' => $nextStage]);

        $ownerEmails = Membership::query()
            ->where('organization_id', $organization->id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin])
            ->with('user')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->all();

        if ($ownerEmails === []) {
            return;
        }

        Mail::to($ownerEmails)->send(new PaymentFailedMail($daysSinceFailure, isFinalNotice: $nextStage === 3));
    }
}
