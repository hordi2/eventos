<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Portail intervenant (D6) : chacun reçoit un lien personnel où il confirme
 * son créneau, dépose son support et lit les informations logistiques. Le
 * support suit le même chemin qu'un fichier joint d'invité — quarantaine
 * hors du dossier public, puis analyse antivirus (§7 du CLAUDE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('speakers', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('company');
            // Lien personnel : un jeton aléatoire, jamais un identifiant
            // devinable (règle 4.6), révocable en le renouvelant.
            $table->string('portal_token', 64)->nullable()->unique()->after('linkedin_url');
            $table->timestamp('portal_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('response_note')->nullable();

            // Support de présentation : un seul par intervenant, le dernier
            // déposé remplace le précédent.
            $table->string('support_disk')->nullable();
            $table->string('support_path')->nullable();
            $table->string('support_original_name')->nullable();
            $table->string('support_mime_type')->nullable();
            $table->bigInteger('support_size_bytes')->nullable();
            $table->string('support_scan_status')->nullable();
            $table->string('support_scan_signature')->nullable();
            $table->timestamp('support_uploaded_at')->nullable();
            $table->timestamp('support_scanned_at')->nullable();
        });

        Schema::table('events', function (Blueprint $table): void {
            // Informations logistiques communes à tous les intervenants :
            // heure d'arrivée, accueil, matériel, personne à contacter.
            $table->text('speaker_briefing')->nullable()->after('room');
        });

        // La RLS s'applique aussi au propriétaire : on se place dans chaque
        // organisation pour donner son lien à chaque intervenant déjà créé.
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::select("select set_config('app.current_organization_id', ?, false)", [(string) $organizationId]);

            foreach (DB::table('speakers')->whereNull('portal_token')->pluck('id') as $id) {
                DB::table('speakers')->where('id', $id)->update(['portal_token' => Str::random(40)]);
            }
        }

        DB::select("select set_config('app.current_organization_id', '', false)");
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('speaker_briefing');
        });

        Schema::table('speakers', function (Blueprint $table): void {
            $table->dropColumn([
                'email',
                'portal_token',
                'portal_sent_at',
                'confirmed_at',
                'declined_at',
                'response_note',
                'support_disk',
                'support_path',
                'support_original_name',
                'support_mime_type',
                'support_size_bytes',
                'support_scan_status',
                'support_scan_signature',
                'support_uploaded_at',
                'support_scanned_at',
            ]);
        });
    }
};
