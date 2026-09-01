<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipRole;
use App\Http\Controllers\Controller;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $isOwner = Membership::query()
            ->where('user_id', $request->user()->id)
            ->where('organization_id', app(CurrentOrganization::class)->id())
            ->where('role', MembershipRole::Owner)
            ->exists();

        return Inertia::render('Dashboard', ['isOwner' => $isOwner]);
    }
}
