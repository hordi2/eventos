<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Form\Actions\ToggleAttendeeCheckIn;
use App\Domain\Form\Models\Attendee;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AttendeeController extends Controller
{
    public function toggleCheckIn(Request $request, int $attendee, ToggleAttendeeCheckIn $action): RedirectResponse
    {
        $action->handle(Attendee::query()->findOrFail($attendee), $request->user());

        return back();
    }
}
