<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accord d'envoi : l'organisation s'engage, une fois, à n'inviter que des
 * personnes qui l'ont accepté. Daté et attribué ; l'acceptation est aussi
 * journalisée dans audit_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->timestamp('sender_agreement_accepted_at')->nullable();
            $table->foreignId('sender_agreement_accepted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sender_agreement_accepted_by');
            $table->dropColumn('sender_agreement_accepted_at');
        });
    }
};
