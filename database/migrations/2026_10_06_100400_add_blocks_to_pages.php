<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page événement en blocs (lot 2) : l'organisateur compose sa page et
 * décide de l'ordre. Une page sans blocs garde la mise en page d'origine
 * (PageBlocks::resolve), le temps que son organisateur l'ouvre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->jsonb('blocks')->nullable()->after('faq_items');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('blocks');
        });
    }
};
