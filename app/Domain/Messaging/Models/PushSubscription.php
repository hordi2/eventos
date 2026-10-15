<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * L'abonnement aux notifications push d'un navigateur, pour une inscription
 * donnée à un événement. event_id et registration_id restent des colonnes
 * simples, jamais des relations Eloquent : Domain/Messaging ne dépend
 * d'aucun modèle de Domain/Event ni de Domain/Form (section 3 du
 * CLAUDE.md, test d'architecture) — même règle que EmailMessage vis-à-vis
 * de Contact.
 */
final class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'organization_id',
        'event_id',
        'registration_id',
        'endpoint',
    ];

    protected static function newFactory(): PushSubscriptionFactory
    {
        return PushSubscriptionFactory::new();
    }
}
