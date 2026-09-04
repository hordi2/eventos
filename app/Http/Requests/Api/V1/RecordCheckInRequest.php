<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\CheckIn\GuestExistsForEvent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class RecordCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attendee_id' => ['required_without:ticket_id', 'prohibits:ticket_id', 'integer'],
            'ticket_id' => ['required_without:attendee_id', 'integer'],
            'device_local_id' => ['required', 'uuid'],
            'direction' => ['required', 'in:check_in,check_out'],
            'recorded_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var GuestExistsForEvent $guestExistsForEvent */
            $guestExistsForEvent = app(GuestExistsForEvent::class);

            $eventId = (int) $this->route('event');
            $attendeeId = $this->integer('attendee_id') ?: null;
            $ticketId = $this->integer('ticket_id') ?: null;

            if (! $guestExistsForEvent->handle($eventId, $attendeeId, $ticketId)) {
                $validator->errors()->add('attendee_id', __("Cet invité n'appartient pas à cet événement."));
            }
        });
    }
}
