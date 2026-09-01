<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un FormField n'est jamais modifié en place une fois créé : soit sa
 * FormVersion est encore un brouillon (auquel cas UpdateFormDraft
 * supprime puis recrée ses champs), soit elle est publiée/archivée et
 * donc figée. Pas de SoftDeletes ici : rien ne justifie de supprimer un
 * champ isolément plutôt que via le remplacement de toute la version.
 */
final class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'form_version_id',
        'key',
        'type',
        'label',
        'help_text',
        'is_required',
        'position',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'is_required' => 'boolean',
            'position' => 'integer',
            'config' => 'array',
        ];
    }

    protected static function newFactory(): FormFieldFactory
    {
        return FormFieldFactory::new();
    }

    /**
     * @return BelongsTo<FormVersion, $this>
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * @return HasMany<FieldOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(FieldOption::class)->orderBy('position');
    }
}
