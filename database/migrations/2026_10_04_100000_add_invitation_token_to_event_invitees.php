<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lien personnel de chaque invité : un jeton aléatoire, jamais un
 * identifiant devinable, qui ouvre directement son invitation quand
 * l'événement est réservé à la liste. Les invités déjà présents reçoivent
 * le leur ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invitees', function (Blueprint $table): void {
            $table->string('invitation_token', 64)->nullable()->unique()->after('contact_id');
        });

        // La RLS s'applique aussi au propriétaire : on se place dans chaque
        // organisation pour compléter ses invités.
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            DB::select("select set_config('app.current_organization_id', ?, false)", [(string) $organizationId]);

            foreach (DB::table('event_invitees')->whereNull('invitation_token')->pluck('id') as $id) {
                DB::table('event_invitees')->where('id', $id)->update(['invitation_token' => Str::random(40)]);
            }
        }

        DB::select("select set_config('app.current_organization_id', '', false)");
    }

    public function down(): void
    {
        Schema::table('event_invitees', function (Blueprint $table): void {
            $table->dropColumn('invitation_token');
        });
    }
};
