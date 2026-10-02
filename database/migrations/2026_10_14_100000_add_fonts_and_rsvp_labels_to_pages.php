<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les polices de l'invitation entre les mains de l'organisateur : celle des
 * titres, celle du texte, et l'écriture manuscrite du « Save the date ».
 * Vides, la page garde celles d'origine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->string('heading_font', 30)->nullable()->after('cover_cta_label');
            $table->string('body_font', 30)->nullable()->after('heading_font');
            $table->string('script_font', 30)->nullable()->after('body_font');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['heading_font', 'body_font', 'script_font']);
        });
    }
};
