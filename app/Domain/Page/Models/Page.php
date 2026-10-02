<?php

declare(strict_types=1);

namespace App\Domain\Page\Models;

use App\Support\MultiTenancy\BelongsToOrganization;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * event_id reste une simple colonne (section 3 du CLAUDE.md).
 */
final class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'event_id',
        'banner_path',
        'cover_eyebrow',
        'cover_script',
        'cover_monogram',
        'cover_overlay',
        'cover_cta_label',
        'heading_font',
        'body_font',
        'script_font',
        'meta_description',
        'program_items',
        'faq_items',
        'blocks',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'program_items' => '[]',
        'faq_items' => '[]',
    ];

    protected function casts(): array
    {
        return [
            'program_items' => 'array',
            'faq_items' => 'array',
            'blocks' => 'array',
        ];
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
