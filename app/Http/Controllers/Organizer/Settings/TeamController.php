<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Organization\Actions\CancelTeamMemberInvitation;
use App\Domain\Organization\Actions\InviteTeamMember;
use App\Domain\Organization\Actions\RemoveTeamMember;
use App\Domain\Organization\Actions\ResendTeamMemberInvitation;
use App\Domain\Organization\Actions\UpdateMembershipRole;
use App\Domain\Organization\InvalidMembershipActionException;
use App\Domain\Organization\Models\Membership;
use App\Domain\Organization\Models\MembershipInvitation;
use App\Domain\Organization\Models\MembershipRole;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\InviteTeamMemberRequest;
use App\Http\Requests\Organizer\Settings\UpdateMembershipRoleRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres → Équipe : inviter des membres à l'échelle de l'organisation
 * (Owner excepté), changer leur rôle, les retirer — distinct de Partage
 * d'événements (Collaborator, sans capacité organisation, événement par
 * événement).
 */
final class TeamController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Team', [
            'members' => $this->members(),
            'invitations' => $this->pendingInvitations(),
            'roleOptions' => MembershipRole::invitableOptions(),
        ]);
    }

    public function store(InviteTeamMemberRequest $request, InviteTeamMember $inviteTeamMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $inviteTeamMember->handle($this->currentOrganization(), $user, $request->validated('email'), $request->role());
        } catch (InvalidMembershipActionException $exception) {
            return back()->withErrors(['email' => $exception->getMessage()]);
        }

        return back()->with('status', 'team-member-invited');
    }

    public function updateRole(UpdateMembershipRoleRequest $request, int $membership, UpdateMembershipRole $updateMembershipRole): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $updateMembershipRole->handle($this->findMembership($membership), $user, $request->role());
        } catch (InvalidMembershipActionException $exception) {
            return back()->withErrors(['membership' => $exception->getMessage()]);
        }

        return back()->with('status', 'team-member-role-updated');
    }

    public function destroy(Request $request, int $membership, RemoveTeamMember $removeTeamMember): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $removeTeamMember->handle($this->findMembership($membership), $user);
        } catch (InvalidMembershipActionException $exception) {
            return back()->withErrors(['membership' => $exception->getMessage()]);
        }

        return back()->with('status', 'team-member-removed');
    }

    public function resendInvitation(Request $request, int $invitation, ResendTeamMemberInvitation $resendTeamMemberInvitation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $resendTeamMemberInvitation->handle($this->findInvitation($invitation), $user);

        return back()->with('status', 'team-member-invitation-resent');
    }

    public function cancelInvitation(Request $request, int $invitation, CancelTeamMemberInvitation $cancelTeamMemberInvitation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cancelTeamMemberInvitation->handle($this->findInvitation($invitation), $user);

        return back()->with('status', 'team-member-invitation-cancelled');
    }

    /**
     * @return list<array{id: int, name: string, email: string, role: string}>
     */
    private function members(): array
    {
        return Membership::query()
            ->where('organization_id', $this->currentOrganization()->id)
            ->with('user:id,name,email')
            ->orderByRaw("role = 'owner' desc")
            ->orderBy('created_at')
            ->get()
            ->filter(fn (Membership $membership): bool => $membership->role !== MembershipRole::Collaborator)
            ->map(fn (Membership $membership): array => [
                'id' => $membership->id,
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, email: string, role: string, status: string}>
     */
    private function pendingInvitations(): array
    {
        return MembershipInvitation::query()
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MembershipInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'status' => $invitation->status(),
            ])
            ->values()
            ->all();
    }

    private function findMembership(int $id): Membership
    {
        return Membership::query()->where('organization_id', $this->currentOrganization()->id)->findOrFail($id);
    }

    private function findInvitation(int $id): MembershipInvitation
    {
        return MembershipInvitation::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
