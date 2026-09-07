<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Réponses à un sujet du forum communautaire — même exception au
 * cloisonnement multi-tenant que community_topics (voir cette migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};
