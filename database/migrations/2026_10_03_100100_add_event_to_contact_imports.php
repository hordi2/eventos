<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un import de contacts peut désormais remplir la liste d'invités d'un
 * événement : chaque contact retenu y est ajouté, avec son groupe, ses
 * accompagnants et sa copie e-mail. Nul pour un import de contacts seul.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_imports', function (Blueprint $table): void {
            $table->foreignId('event_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_imports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
