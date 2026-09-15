<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inscription à un événement secondaire (T-013) : une Registration ordinaire
 * sur le sous-événement, rattachée à l'inscription principale qui l'a
 * créée. Elle garde ainsi sa propre capacité, sa liste et son check-in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->foreignId('parent_registration_id')->nullable()->constrained('registrations')->nullOnDelete();
            $table->index('parent_registration_id');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_registration_id');
        });
    }
};
