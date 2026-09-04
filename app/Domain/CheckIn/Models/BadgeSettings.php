<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\BadgeSettingsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * event_id reste une simple colonne (section 3 du CLAUDE.md) : un seul
 * réglage par événement, retrouvé par event_id plutôt que par une relation.
 */
final class BadgeSettings extends Model
{
    /** @use HasFactory<BadgeSettingsFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'logo_path',
    ];

    protected static function newFactory(): BadgeSettingsFactory
    {
        return BadgeSettingsFactory::new();
    }
}
