<?php

declare(strict_types=1);

namespace App\Domain\Community\Models;

enum CommunityCategory: string
{
    case EventManagement = 'event_management';
    case FormBuilding = 'form_building';
    case Troubleshooting = 'troubleshooting';
    case Suggestions = 'suggestions';

    public function label(): string
    {
        return match ($this) {
            self::EventManagement => "Gestion d'événement",
            self::FormBuilding => 'Construction de formulaire',
            self::Troubleshooting => 'Dépannage',
            self::Suggestions => 'Suggestions',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::EventManagement => 'Retours d\'expérience, organisation le jour J',
            self::FormBuilding => 'Logique conditionnelle, cas particuliers',
            self::Troubleshooting => 'Intégrations, comportements inattendus',
            self::Suggestions => 'Fonctionnalités que vous aimeriez voir arriver',
        };
    }
}
