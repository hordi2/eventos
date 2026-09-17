<?php

declare(strict_types=1);

namespace App\Domain\Form\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\RegistrationFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fichier joint envoyé par un invité (bloc « Fichier joint »). Rangé hors du
 * dossier public, en quarantaine jusqu'au verdict de ClamAV. registration_id
 * reste nul tant que l'inscription n'est pas confirmée ; event_id est une
 * simple colonne (Domain/Form ne dépend pas de Domain/Event).
 *
 * Conservé tant que l'inscription existe (décision produit) : supprimé à
 * l'effacement RGPD du contact, ou 30 jours après l'envoi s'il n'a jamais
 * été rattaché à une inscription.
 */
final class RegistrationFile extends Model
{
    /** @use HasFactory<RegistrationFileFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    public const QUARANTINE_DIRECTORY = 'registration-files/quarantaine';

    public const STORAGE_DIRECTORY = 'registration-files';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'form_field_id',
        'registration_draft_id',
        'registration_id',
        'token',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'scan_status',
        'scan_signature',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_status' => FileScanStatus::class,
            'size_bytes' => 'integer',
            'scanned_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): RegistrationFileFactory
    {
        return RegistrationFileFactory::new();
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return BelongsTo<FormField, $this>
     */
    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
