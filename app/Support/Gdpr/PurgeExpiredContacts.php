<?php

declare(strict_types=1);

namespace App\Support\Gdpr;

use App\Domain\Contact\Models\Contact;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tâche planifiée quotidienne (T-075, AC : « job planifié de purge selon
 * les durées configurées »). withoutGlobalScopes() : parcourt toutes les
 * organisations, même raisonnement que CheckQuotaAlerts/ProcessDunning —
 * CurrentOrganization est positionné une par une avant toute lecture
 * cloisonnée.
 *
 * La dernière activité d'un contact est sa plus récente inscription
 * (registrations.registered_at), ou sa date de création si aucune : un
 * contact jamais inscrit mais ajouté récemment (import, saisie manuelle)
 * ne doit pas être anonymisé au premier passage de la tâche.
 */
final class PurgeExpiredContacts
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
        private readonly AnonymizeContact $anonymizeContact,
    ) {}

    public function handle(): int
    {
        $anonymizedCount = 0;

        foreach (Organization::query()->withoutGlobalScopes()->get() as $organization) {
            $anonymizedCount += $this->purgeForOrganization($organization);
        }

        return $anonymizedCount;
    }

    private function purgeForOrganization(Organization $organization): int
    {
        $this->currentOrganization->set($organization);

        $cutoff = CarbonImmutable::now()->subMonths((int) config('gdpr.contact_retention_months'));

        $expiredContactIds = DB::table('contacts')
            ->leftJoin('registrations', 'registrations.contact_id', '=', 'contacts.id')
            ->where('contacts.organization_id', $organization->id)
            ->whereNull('contacts.deleted_at')
            ->groupBy('contacts.id', 'contacts.created_at')
            ->havingRaw('coalesce(max(registrations.registered_at), contacts.created_at) < ?', [$cutoff])
            ->pluck('contacts.id');

        // L'utilisateur "système" causant l'anonymisation automatique n'a
        // pas de session : AnonymizeContact exige un User authentifiable
        // pour sa vérification de capacité (Gate::forUser), résolue ici via
        // le premier membre Owner de l'organisation plutôt qu'une exception
        // dédiée pour ce seul cas d'automatisation.
        $systemUser = User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('organization_id', $organization->id)
                ->whereIn('role', [MembershipRole::Owner, MembershipRole::Admin, MembershipRole::Editor]))
            ->first();

        if ($systemUser === null) {
            return 0;
        }

        $count = 0;

        foreach ($expiredContactIds as $contactId) {
            $contact = Contact::query()->find($contactId);

            if ($contact === null) {
                continue;
            }

            try {
                $this->anonymizeContact->handle($contact, $systemUser);
                $count++;
            } catch (Throwable $exception) {
                Log::error("Échec de la purge RGPD automatique du contact #{$contactId} : {$exception->getMessage()}");
            }
        }

        return $count;
    }
}
