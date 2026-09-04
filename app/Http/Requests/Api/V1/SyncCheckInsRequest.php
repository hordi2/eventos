<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\CheckIn\GuestExistsForEvent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class SyncCheckInsRequest extends FormRequest
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
            'scans' => ['required', 'array', 'min:1', 'max:500'],
            'scans.*.attendee_id' => ['required_without:scans.*.ticket_id', 'prohibits:scans.*.ticket_id', 'integer'],
            'scans.*.ticket_id' => ['required_without:scans.*.attendee_id', 'integer'],
            'scans.*.device_local_id' => ['required', 'uuid', 'distinct'],
            'scans.*.direction' => ['required', 'in:check_in,check_out'],
            'scans.*.recorded_at' => ['required', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var GuestExistsForEvent $guestExistsForEvent */
            $guestExistsForEvent = app(GuestExistsForEvent::class);

            $eventId = (int) $this->route('event');

            foreach ((array) $this->input('scans', []) as $index => $scan) {
                $attendeeId = isset($scan['attendee_id']) ? (int) $scan['attendee_id'] : null;
                $ticketId = isset($scan['ticket_id']) ? (int) $scan['ticket_id'] : null;

                if (! $guestExistsForEvent->handle($eventId, $attendeeId, $ticketId)) {
                    $validator->errors()->add("scans.{$index}", __("Cet invité n'appartient pas à cet événement."));
                }
            }
        });
    }
}
