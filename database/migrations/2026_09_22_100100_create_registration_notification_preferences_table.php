<?php

declare(strict_types=1);

use App\Support\MultiTenancy\OrganizationRowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paramètres → Notifications (demande utilisateur) : préférence personnelle
 * par événement, une ligne par (utilisateur, événement) — seulement créée
 * quand un organisateur s'écarte du réglage par défaut (tout activé), pour
 * ne pas devoir insérer une ligne par membre à chaque création d'événement.
 * Le réglage général (`users.registration_notifications_enabled`) fait
 * office d'interrupteur maître, vérifié en premier par le listener avant
 * même de consulter cette table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('registration_notifications_enabled')->default(true)->after('mfa_email_enabled');
        });

        Schema::create('registration_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->boolean('notify_created')->default(true);
            $table->boolean('notify_updated')->default(true);
            $table->boolean('notify_cancelled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
            $table->index(['organization_id', 'event_id']);
        });

        OrganizationRowLevelSecurity::enable('registration_notification_preferences');
    }

    public function down(): void
    {
        OrganizationRowLevelSecurity::disable('registration_notification_preferences');
        Schema::dropIfExists('registration_notification_preferences');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('registration_notifications_enabled');
        });
    }
};
