<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajouté par anticipation pour T-042 (segments « présents »/« no-show »),
 * marqué manuellement en attendant le vrai check-in (T-060/061 : scan QR,
 * mode hors ligne, device_local_id). Cette colonne pourra être reprise ou
 * complétée par une structure dédiée quand ces tickets seront construits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->timestamp('checked_in_at')->nullable()->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('attendees', function (Blueprint $table): void {
            $table->dropColumn('checked_in_at');
        });
    }
};
