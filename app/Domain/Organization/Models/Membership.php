<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

use App\Models\User;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Table de résolution du tenant : appartient volontairement à une organisation
 * sans utiliser BelongsToOrganization ni la row-level security applicables aux
 * données métier. On l'interroge justement pour déterminer l'organisation
 * courante — elle ne peut donc pas dépendre d'un contexte déjà résolu.
 */
final class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
        ];
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
