<?php

declare(strict_types=1);

namespace App\Domain\Page\Models;

use App\Support\Antivirus\FileScanStatus;
use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PageMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un fichier audio ou vidéo déposé dans Itaza pour l'invitation : le mot
 * d'accueil enregistré par les mariés, par exemple. Tant que l'analyse
 * antivirus n'a pas conclu, il ne se joue pas.
 */
final class PageMedia extends Model
{
    /** @use HasFactory<PageMediaFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $table = 'page_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'uploaded_by',
        'token',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'scan_status',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_status' => FileScanStatus::class,
            'scanned_at' => 'immutable_datetime',
            'size_bytes' => 'integer',
        ];
    }

    protected static function newFactory(): PageMediaFactory
    {
        return PageMediaFactory::new();
    }

    /** Un média ne se joue que sain : refusé ou en attente, il reste muet. */
    public function isPlayable(): bool
    {
        return $this->scan_status === FileScanStatus::Clean;
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }
}
