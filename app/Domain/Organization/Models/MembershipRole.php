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
}
