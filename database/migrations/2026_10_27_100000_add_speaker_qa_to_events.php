<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Questions au conférencier (D9) : l'organisateur ouvre ou ferme la
 * fonctionnalité par événement, indépendamment du reste de l'application
 * participant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('has_speaker_qa')->default(false)->after('has_participant_app');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('has_speaker_qa');
        });
    }
};
