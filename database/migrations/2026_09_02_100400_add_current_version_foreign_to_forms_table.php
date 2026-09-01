<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->foreign('current_version_id')->references('id')->on('form_versions')->nullOnDelete();
            $table->index('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table): void {
            $table->dropForeign(['current_version_id']);
            $table->dropIndex(['current_version_id']);
        });
    }
};
