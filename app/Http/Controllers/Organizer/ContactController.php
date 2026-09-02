<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organizer;

use App\Domain\Contact\Actions\CreateContact;
use App\Domain\Contact\Actions\UpdateContact;
use App\Domain\Contact\Models\Contact;
use App\Domain\Form\Models\Registration;
use App\Domain\Organization\Models\Organization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\Contact\SaveContactRequest;
use App\Support\MultiTenancy\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', [Contact::class, $this->currentOrganization()]);

        $search = trim((string) $request->query('q', ''));

        $contacts = Contact::query()
            ->with('household')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Contact $contact): array => [
                'id' => $contact->id,
                'full_name' => $contact->fullName(),
                'email' => $contact->email,
                'phone_e164' => $contact->phone_e164,
                'household_name' => $contact->household?->name,
            ]);

        return Inertia::render('Contacts/Index', ['contacts' => $contacts, 'search' => $search]);
    }

    public function create(): Response
    {
        Gate::authorize('create', [Contact::class, $this->currentOrganization()]);

        return Inertia::render('Contacts/Form', ['contact' => null]);
    }

    public function store(SaveContactRequest $request, CreateContact $action): RedirectResponse
    {
        $contact = $action->handle($this->currentOrganization(), $request->user(), $request->validated());

        return redirect()->route('contacts.edit', $contact);
    }

    public function edit(int $contact): Response
    {
        $contact = $this->findContact($contact);

        Gate::authorize('view', $contact);

        $history = Registration::query()
            ->where('contact_id', $contact->id)
            ->with('formVersion')
            ->latest('registered_at')
            ->get()
            ->map(fn (Registration $registration): array => [
                'id' => $registration->id,
                'event_id' => $registration->event_id,
                'status' => $registration->status->value,
                'registered_at' => $registration->registered_at->toIso8601String(),
            ]);

        return Inertia::render('Contacts/Form', [
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'email' => $contact->email,
                'phone_e164' => $contact->phone_e164,
                'company' => $contact->company,
                'job_title' => $contact->job_title,
                'preferred_language' => $contact->preferred_language,
                'preferred_channel' => $contact->preferred_channel,
                'household_name' => $contact->household?->name,
                'email_consent' => $contact->email_consent,
                'email_consent_source' => $contact->email_consent_source,
                'email_consent_at' => $contact->email_consent_at?->toIso8601String(),
                'sms_consent' => $contact->sms_consent,
                'sms_consent_source' => $contact->sms_consent_source,
                'sms_consent_at' => $contact->sms_consent_at?->toIso8601String(),
                'whatsapp_consent' => $contact->whatsapp_consent,
                'whatsapp_consent_source' => $contact->whatsapp_consent_source,
                'whatsapp_consent_at' => $contact->whatsapp_consent_at?->toIso8601String(),
            ],
            'history' => $history,
        ]);
    }

    public function update(SaveContactRequest $request, int $contact, UpdateContact $action): RedirectResponse
    {
        $updated = $action->handle($this->findContact($contact), $request->user(), $request->validated());

        return redirect()->route('contacts.edit', $updated);
    }

    private function findContact(int $id): Contact
    {
        return Contact::query()->findOrFail($id);
    }

    private function currentOrganization(): Organization
    {
        return Organization::query()->findOrFail(app(CurrentOrganization::class)->requireId());
    }
}
