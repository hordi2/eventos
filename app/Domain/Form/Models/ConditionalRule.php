<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\ConditionalRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * condition_group : arbre imbriqué ET/OU, ex.
 * ['combinator' => 'and', 'conditions' => [
 *     ['field_key' => 'diner', 'operator' => 'is', 'value' => 'oui'],
 *     ['combinator' => 'or', 'conditions' => [...]],
 * ]]
 * Pas de SoftDeletes : une règle appartient à une FormVersion qui suit déjà
 * le cycle de vie brouillon/publiée/archivée du formulaire — elle n'a pas de
 * cycle de vie propre à préserver.
 */
final class ConditionalRule extends Model
{
    /** @use HasFactory<ConditionalRuleFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'form_version_id',
        'target_field_id',
        'action',
        'condition_group',
    ];

    protected function casts(): array
    {
        return [
            'action' => RuleAction::class,
            'condition_group' => 'array',
        ];
    }

    protected static function newFactory(): ConditionalRuleFactory
    {
        return ConditionalRuleFactory::new();
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function targetField(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'target_field_id');
    }
}
