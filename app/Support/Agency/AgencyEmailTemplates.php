<?php

declare(strict_types=1);

namespace App\Support\Agency;

use App\Domain\Messaging\Models\EmailTemplate;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Support\Facades\Gate;

/**
 * Les modèles d'e-mail qu'une agence partage avec ses comptes clients (D10).
 *
 * Un compte confié à une agence voit les modèles qu'elle partage, et peut
 * les reprendre chez lui : la copie lui appartient alors, il l'adapte sans
 * toucher à celle de l'agence. Rien n'est lu chez l'agence sans passer par
 * son contexte — la row-level security ne laisse voir une organisation que
 * lorsqu'elle est posée (règle 4.1).
 *
 * Traverse Organization et Messaging : sa place est dans Support (section 3
 * du CLAUDE.md).
 */
final class AgencyEmailTemplates
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * Les modèles que l'agence du compte partage avec lui. Un compte sans
     * agence n'en a aucun.
     *
     * @return list<array{id: int, name: string, subject: string, agency: string}>
     */
    public function shared(Organization $client): array
    {
        $agency = $this->agencyOf($client);

        if ($agency === null) {
            return [];
        }

        return $this->inContext($agency, fn (): array => EmailTemplate::query()
            ->where('is_shared_with_clients', true)
            ->orderBy('name')
            ->get(['id', 'name', 'subject'])
            ->map(fn (EmailTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'subject' => $template->subject,
                'agency' => $agency->name,
            ])
            ->values()
            ->all());
    }

    /**
     * Reprend chez le client un modèle partagé par son agence. La copie est
     * à lui : la modifier ne change rien chez l'agence.
     */
    public function import(Organization $client, User $user, int $templateId): EmailTemplate
    {
        Gate::forUser($user)->authorize('create', [EmailTemplate::class, $client]);

        $agency = $this->agencyOf($client);
        abort_if($agency === null, 404);

        $shared = $this->inContext($agency, fn (): ?EmailTemplate => EmailTemplate::query()
            ->where('is_shared_with_clients', true)
            ->find($templateId));

        abort_if($shared === null, 404);

        return EmailTemplate::query()->create([
            'organization_id' => $client->id,
            'created_by' => $user->id,
            'name' => $shared->name,
            'subject' => $shared->subject,
            'blocks' => $shared->blocks,
            // La copie ne se repartage pas d'elle-même : c'est à l'agence
            // de décider ce qu'elle diffuse.
            'is_shared_with_clients' => false,
        ]);
    }

    private function agencyOf(Organization $client): ?Organization
    {
        if ($client->managed_by_organization_id === null) {
            return null;
        }

        return Organization::query()->find($client->managed_by_organization_id);
    }

    /**
     * Exécute une lecture dans le contexte d'une autre organisation, puis
     * remet celui de départ — quoi qu'il arrive.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $read
     * @return TValue
     */
    private function inContext(Organization $organization, callable $read): mixed
    {
        $previous = $this->currentOrganization->id();
        $this->currentOrganization->set($organization);

        try {
            return $read();
        } finally {
            $previous === null ? $this->currentOrganization->clear() : $this->currentOrganization->set($previous);
        }
    }
}
