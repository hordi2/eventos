<?php

declare(strict_types=1);

namespace App\Domain\CheckIn\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\SeatingTableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * event_id reste une simple colonne (section 3 du CLAUDE.md).
 */
final class SeatingTable extends Model
{
    /** @use HasFactory<SeatingTableFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'name',
        'shape',
        'capacity',
        'position_x',
        'position_y',
        'width',
        'height',
        'rotation',
    ];

    protected function casts(): array
    {
        return [
            'shape' => SeatingTableShape::class,
            // PDO renvoie les colonnes float PostgreSQL sous forme de
            // chaînes sans cast explicite — trouvé en testant l'éditeur
            // visuel, où position_x/position_y revenaient en JSON comme
            // "250" plutôt que 250.
            'position_x' => 'float',
            'position_y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
        ];
    }

    protected static function newFactory(): SeatingTableFactory
    {
        return SeatingTableFactory::new();
    }

    /**
     * @return HasMany<SeatAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(SeatAssignment::class);
    }
}
