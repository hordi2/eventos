<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Models\User;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\OrganizationImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Image de la bibliothèque d'une organisation : envoyée une fois pour un
 * logo, un fond ou un bloc, puis réutilisable partout. Les dimensions sont
 * nulles pour les images reprises d'avant la bibliothèque (voir la migration).
 */
final class OrganizationImage extends Model
{
    /** @use HasFactory<OrganizationImageFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const DIRECTORY = 'organization-images';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function newFactory(): OrganizationImageFactory
    {
        return OrganizationImageFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
