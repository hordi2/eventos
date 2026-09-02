<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\Auditing\Auditable;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\FormVersionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FormVersion extends Model
{
    /** @use HasFactory<FormVersionFactory> */
    use Auditable, BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'form_id',
        'version_number',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FormVersionStatus::class,
            'version_number' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): FormVersionFactory
    {
        return FormVersionFactory::new();
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    /**
     * @return HasMany<ConditionalRule, $this>
     */
    public function conditionalRules(): HasMany
    {
        return $this->hasMany(ConditionalRule::class);
    }

    public function isEditableInPlace(): bool
    {
        return $this->status === FormVersionStatus::Draft;
    }
}
