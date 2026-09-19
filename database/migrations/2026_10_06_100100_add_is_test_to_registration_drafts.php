<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simulation d'inscription lancée par l'organisateur (« Prévisualiser ») :
 * le parcours invité complet, mais le brouillon ne devient jamais une
 * inscription.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registration_drafts', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('resume_token');
        });
    }

    public function down(): void
    {
        Schema::table('registration_drafts', function (Blueprint $table): void {
            $table->dropColumn('is_test');
        });
    }
};
