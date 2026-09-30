<?php

declare(strict_types=1);

namespace App\Domain\Event\Models;

use App\Support\Auditing\Auditable;
use App\Support\Casts\AsMoney;
use App\Support\Money;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\BudgetLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ligne du budget d'un événement (D7) : un poste de dépense, ou une recette
 * attendue hors billetterie.
 *
 * @property Money $planned
 * @property ?Money $actual
 */
final class BudgetLine extends Model
{
    /** @use HasFactory<BudgetLineFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'kind',
        'category',
        'label',
        'supplier',
        'note',
        'planned',
        'actual',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'kind' => BudgetLineKind::class,
            'category' => BudgetCategory::class,
            'planned' => AsMoney::class.':planned_amount_minor,planned_currency',
            'actual' => AsMoney::class.':actual_amount_minor,actual_currency',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): BudgetLineFactory
    {
        return BudgetLineFactory::new();
    }

    /**
     * Dépense engagée au-delà de ce qui était prévu : c'est elle qui
     * déclenche l'alerte de dépassement. Une recette qui rentre moins bien
     * que prévu se lit dans le résultat, pas comme un dépassement.
     */
    public function isOverrun(): bool
    {
        return $this->kind === BudgetLineKind::Expense
            && $this->actual !== null
            && $this->actual->amountMinor() > $this->planned->amountMinor();
    }
}
