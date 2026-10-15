<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application participant en marque blanche (D9) : programme, plan,
 * notifications, networking, questions au conférencier et sondages en
 * direct, réunis dans une page web installable aux couleurs de
 * l'organisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->boolean('has_participant_app')->default(false)->after('has_carbon_report');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('has_participant_app');
        });
    }
};
