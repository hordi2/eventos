<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accompagnants nommés (T-032) : chaque personne d'une inscription est un
 * Attendee avec son propre QR (qr_jti, révocable — §4.6 du CLAUDE.md), et
 * une question posée « à chaque personne » garde une réponse par
 * participant (attendee_id). Les réponses du titulaire restent sans
 * attendee_id, exactement comme avant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->uuid('qr_jti')->nullable()->unique();
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::table('registration_answers', function (Blueprint $table): void {
            $table->foreignId('attendee_id')->nullable()->constrained('attendees')->cascadeOnDelete();
            $table->dropUnique(['registration_id', 'form_field_id']);
            $table->index('attendee_id');
        });

        // PostgreSQL tient deux NULL pour distincts : une contrainte unique
        // ordinaire laisserait passer deux réponses du titulaire au même
        // champ. D'où deux index uniques partiels.
        DB::statement('CREATE UNIQUE INDEX registration_answers_primary_unique ON registration_answers (registration_id, form_field_id) WHERE attendee_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX registration_answers_attendee_unique ON registration_answers (attendee_id, form_field_id) WHERE attendee_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS registration_answers_attendee_unique');
        DB::statement('DROP INDEX IF EXISTS registration_answers_primary_unique');

        Schema::table('registration_answers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('attendee_id');
            $table->unique(['registration_id', 'form_field_id']);
        });

        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropUnique(['qr_jti']);
            $table->dropColumn(['qr_jti', 'position']);
        });
    }
};
