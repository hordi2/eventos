<?php

declare(strict_types=1);

namespace App\Domain\Organization\Actions;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Mail\QuotaAlertMail;
use App\Support\Billing\Data\OrganizationUsageData;
use App\Support\Billing\GetOrganizationUsage;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

/**
 * Tâche planifiée quotidienne (AC T-074 : « alerte à 80 % et 100 % de
 * quota »). withoutGlobalScopes() : parcourt toutes les organisations,
 * même raisonnement que ReconcileMobileMoneyPayments — CurrentOrganization
 * est positionné une par une avant toute lecture cloisonnée.
 */
final class CheckQuotaAlerts
{
    /**
     * @var array<string, string>
     */
    private const METRIC_LABELS = [
        'registrations' => 'inscriptions',
        'emails' => 'e-mails',
        'active_events' => 'événements actifs',
    ];

    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly GetOrganizationUsage $getOrganizationUsage,
    ) {}

    public function handle(): void
    {
        $organizations = Organization::query()->withoutGlobalScopes()->get();

        foreach ($organizations as $organization) {
            $this->checkOne($organization);
        }
    }

    private function checkOne(Organization $organization): void
    {
        $this->currentOrganization->set($organization);
        $usage = $this->getOrganizationUsage->handle($organization);
        $period = now()->format('Y-m');

        foreach ($usage->percentages() as $metric => $percentage) {
            if ($percentage === null) {
                continue;
            }

            // Seuil le plus haut déjà atteint seulement : évite d'envoyer
            // à la fois l'alerte 80 % et 100 % le même jour si le quota a
            // été franchi d'un coup (import massif, par exemple).
            $threshold = match (true) {
                $percentage >= 100 => 100,
                $percentage >= 80 => 80,
                default => null,
            };

            if ($threshold !== null) {
                $this->maybeAlert($organization, $usage, $metric, $threshold, $period);
            }
        }
    }

    private function maybeAlert(Organization $organization, OrganizationUsageData $usage, string $metric, int $threshold, string $period): void
    {
        $inserted = DB::table('quota_alerts')->insertOrIgnore([[
            'organization_id' => $organization->id,
            'metric' => $metric,
            'threshold' => $threshold,
            'period' => $period,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]]);

        if ($inserted === 0) {
            return;
        }

        [$used, $quota] = match ($metric) {
            'registrations' => [$usage->registrationsThisMonth, $usage->registrationsQuota],
            'emails' => [$usage->emailsThisMonth, $usage->emailsQuota],
            'active_events' => [$usage->activeEvents, $usage->activeEventsQuota],
            default => throw new LogicException("Métrique de quota inconnue : {$metric}."),
        };

        $ownerEmails = Membership::query()
            ->where('organization_id', $organization->id)
            ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin])
            ->with('user')
            ->get()
            ->pluck('user.email')
            ->filter()
            ->all();

        if ($ownerEmails === [] || $quota === null) {
            return;
        }

        Mail::to($ownerEmails)->send(new QuotaAlertMail(self::METRIC_LABELS[$metric], $threshold, $used, $quota));
    }
}
