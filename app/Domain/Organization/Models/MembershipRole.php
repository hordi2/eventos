<?php

declare(strict_types=1);

namespace App\Domain\Organization\Models;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case DoorStaff = 'door_staff';
    case Viewer = 'viewer';

    // Aucune capacité à l'échelle de l'organisation : ses droits viennent
    // uniquement des événements partagés avec lui (CollaboratorPermission).
    case Collaborator = 'collaborator';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Propriétaire',
            self::Admin => 'Administrateur',
            self::Editor => 'Éditeur',
            self::DoorStaff => "Personnel d'accueil",
            self::Viewer => 'Lecture seule',
            self::Collaborator => 'Collaborateur',
        };
    }

    /**
     * Rôles qu'on peut attribuer en invitant un membre (Paramètres → Équipe) :
     * ni Owner (le transfert de propriété est une décision à part), ni
     * Collaborator (son propre circuit d'invitation, par événement, voir
     * Partage d'événements).
     *
     * @return list<self>
     */
    public static function invitable(): array
    {
        return [self::Admin, self::Editor, self::DoorStaff, self::Viewer];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function invitableOptions(): array
    {
        return array_map(fn (self $role): array => ['value' => $role->value, 'label' => $role->label()], self::invitable());
    }
}
