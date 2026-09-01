<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Membership;
use App\Support\MultiTenancy\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCurrentOrganization
{
    public function __construct(
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $organizationId = $request->session()->get('current_organization_id');

            if ($organizationId === null) {
                $organizationId = Membership::query()
                    ->where('user_id', $user->id)
                    ->value('organization_id');
            }

            if ($organizationId !== null) {
                $this->currentOrganization->set((int) $organizationId);
            }
        }

        return $next($request);
    }
}
