<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\FieldOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FieldOption extends Model
{
    /** @use HasFactory<FieldOptionFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'form_field_id',
        'value',
        'label',
        'position',
        'quota',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'quota' => 'integer',
        ];
    }

    protected static function newFactory(): FieldOptionFactory
    {
        return FieldOptionFactory::new();
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
