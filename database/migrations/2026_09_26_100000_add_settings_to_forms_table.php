<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            // Présentation du formulaire (écrans d'accueil, de confirmation
            // et de refus, thème) : portée par le formulaire et non par ses
            // versions, puisqu'elle ne change jamais l'interprétation d'une
            // réponse déjà collectée (règle 4.7 du CLAUDE.md).
            $table->jsonb('settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropColumn('settings');
        });
    }
};
