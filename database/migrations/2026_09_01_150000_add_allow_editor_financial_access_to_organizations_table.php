<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            // Matrice M0.3 : l'accès de l'éditeur aux données financières et
            // aux exports est "⚙️ paramétrable par le propriétaire" — pas un
            // rôle figé, un interrupteur par organisation.
            $table->boolean('allow_editor_financial_access')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn('allow_editor_financial_access');
        });
    }
};
