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
}
