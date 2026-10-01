<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Livre d'or d'un événement (D1) : les mots que les invités laissent sur la
 * page publique. Publiés aussitôt — c'est ce qui en fait la joie —, et
 * masquables d'un geste par l'organisateur, qui reste maître de sa page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_book_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();

            $table->string('author_name');
            $table->text('message');
            $table->boolean('is_published')->default(true);
            // Qui a écrit, vu du serveur : jamais affiché, gardé le temps de
            // retrouver l'auteur d'un abus puis effacé avec le message.
            $table->string('author_ip', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'event_id']);
            $table->index(['event_id', 'is_published']);
        });

        OrganizationRowLevelSecurity::enable('guest_book_messages');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('guest_book_messages');
        Schema::dropIfExists('guest_book_messages');
    }
};
