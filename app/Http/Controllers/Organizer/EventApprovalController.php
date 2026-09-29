<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Event\Models\Event;
use App\Domain\Form\Actions\ApproveRegistration;
use App\Domain\Form\Actions\RejectRegistration;
use App\Domain\Form\InvalidRegistrationDecisionException;
use App\Domain\Form\Models\Registration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Event\RejectRegistrationRequest;
use App\Models\User;
use App\Support\Registration\PresentPendingRegistrations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Inscriptions à valider » d'un événement en validation manuelle
 * (M1.2) : accepter ou refuser chaque demande.
 */
final class EventApprovalController extends Controller
{
    public function index(int $event, PresentPendingRegistrations $presentPendingRegistrations): Response
    {
        $eventModel = Event::query()->findOrFail($event);
        Gate::authorize('viewGuests', $eventModel->organization);

        return Inertia::render('Events/Approvals', [
            'event' => ['id' => $eventModel->id, 'title' => $eventModel->title, 'requiresApproval' => $eventModel->requires_approval],
            'registrations' => $presentPendingRegistrations->handle($eventModel),
            'canDecide' => Gate::allows('updateGuests', $eventModel->organization),
            'settingsUrl' => route('events.edit', $eventModel->id),
        ]);
    }

    public function approve(Request $request, int $registration, ApproveRegistration $approveRegistration): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->decide(fn () => $approveRegistration->handle($this->registration($registration), $user), 'registration-approved');
    }

    public function reject(RejectRegistrationRequest $request, int $registration, RejectRegistration $rejectRegistration): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reason = $request->validated('reason');

        return $this->decide(fn () => $rejectRegistration->handle($this->registration($registration), $user, $reason), 'registration-rejected');
    }

    private function registration(int $id): Registration
    {
        return Registration::query()->whereNull('parent_registration_id')->findOrFail($id);
    }

    private function decide(callable $decision, string $status): RedirectResponse
    {
        try {
            $decision();
        } catch (InvalidRegistrationDecisionException $exception) {
            return back()->withErrors(['decision' => $exception->getMessage()]);
        }

        return back()->with('status', $status);
    }
}
