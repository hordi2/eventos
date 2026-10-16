<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SeatingAccessibilityNeedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * event_id/guest_id restent de simples colonnes (section 3 du CLAUDE.md).
 * Pas d'updated_at : l'existence de la ligne est l'information, rien à
 * mettre à jour dessus — on la crée ou on la supprime.
 */
final class SeatingAccessibilityNeed extends Model
{
    /** @use HasFactory<SeatingAccessibilityNeedFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'guest_type',
        'guest_id',
    ];

    protected static function newFactory(): SeatingAccessibilityNeedFactory
    {
        return SeatingAccessibilityNeedFactory::new();
    }
}
