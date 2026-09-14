<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer\Settings;

use App\Domain\Event\Models\Event;
use App\Domain\Organization\Actions\InviteCollaborator;
use App\Domain\Organization\Actions\RemoveCollaborator;
use App\Domain\Organization\Actions\ResendCollaboratorInvitation;
use App\Domain\Organization\Actions\UpdateCollaboratorPermissions;
use App\Domain\Organization\Models\Collaborator;
use App\Domain\Organization\Models\CollaboratorEventPermission;
use App\Domain\Organization\Models\CollaboratorPermission;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Settings\SaveCollaboratorRequest;
use App\Models\User;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Paramètres → Partage d'événements : inviter des collaborateurs et du
 * personnel d'accueil sur des événements précis, avec une permission par
 * événement.
 */
final class EventSharingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/EventSharing', [
            'collaborators' => $this->collaborators(),
            'events' => $this->events(),
            'permissionOptions' => array_map(
                fn (CollaboratorPermission $permission): array => ['value' => $permission->value, 'label' => $permission->label()],
                CollaboratorPermission::cases(),
            ),
        ]);
    }

    public function store(SaveCollaboratorRequest $request, InviteCollaborator $inviteCollaborator): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $inviteCollaborator->handle(
            $this->currentOrganization(),
            $user,
            $request->string('email')->toString(),
            $request->permissionsByEvent(),
        );

        return back()->with('status', 'collaborator-invited');
    }

    public function update(SaveCollaboratorRequest $request, int $collaborator, UpdateCollaboratorPermissions $updateCollaboratorPermissions): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $updateCollaboratorPermissions->handle($this->findCollaborator($collaborator), $user, $request->permissionsByEvent());

        return back()->with('status', 'collaborator-updated');
    }

    public function resend(Request $request, int $collaborator, ResendCollaboratorInvitation $resendCollaboratorInvitation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $collaboratorModel = $this->findCollaborator($collaborator);

        abort_if($collaboratorModel->isAccepted(), 409, 'Cette personne a déjà accepté son invitation.');

        $resendCollaboratorInvitation->handle($collaboratorModel, $user);

        return back()->with('status', 'collaborator-invitation-resent');
    }

    public function destroy(Request $request, int $collaborator, RemoveCollaborator $removeCollaborator): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $removeCollaborator->handle($this->findCollaborator($collaborator), $user);

        return back()->with('status', 'collaborator-removed');
    }

    /**
     * @return list<array{id: int, email: string, name: ?string, status: string, permissions: list<array{event_id: int, permission: string}>}>
     */
    private function collaborators(): array
    {
        return Collaborator::query()
            ->with(['eventPermissions', 'user:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Collaborator $collaborator): array => [
                'id' => $collaborator->id,
                'email' => $collaborator->email,
                'name' => $collaborator->user?->name,
                'status' => $collaborator->status(),
                'permissions' => $collaborator->eventPermissions
                    ->reject(fn (CollaboratorEventPermission $row): bool => $row->permission === CollaboratorPermission::None)
                    ->map(fn (CollaboratorEventPermission $row): array => ['event_id' => $row->event_id, 'permission' => $row->permission->value])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, title: string, date: string}>
     */
    private function events(): array
    {
        return Event::query()
            ->orderByDesc('start_at')
            ->get(['id', 'title', 'start_at', 'timezone'])
            ->map(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->start_at->setTimezone($event->timezone)->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    private function findCollaborator(int $id): Collaborator
    {
        return Collaborator::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
