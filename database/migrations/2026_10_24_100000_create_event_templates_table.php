<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliothèque de modèles d'événement, partagée entre organisations (D11).
 *
 * Volontairement sans row-level security, contrairement à toutes les autres
 * tables portant un organization_id : une bibliothèque communautaire n'a de
 * sens que si chacun voit les modèles des autres. La colonne dit qui a
 * publié ; elle ne cloisonne pas la lecture.
 *
 * Un modèle ne porte que la structure d'un événement — son formulaire, sa
 * page, son programme. Jamais d'invités, jamais de dates, jamais de montants :
 * ce qui se partage, c'est une façon de faire, pas les données de personne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('published_by')->constrained('users');

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->string('category');
            $table->jsonb('payload');

            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('uses_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'category']);
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
