<?php

declare(strict_types=1);

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTicketOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // Généré par TicketOrderController::show() et reporté tel quel
            // par le formulaire : sert de reservation_key à CreateOrder, ce
            // qui rend un double envoi (retour navigateur, double clic)
            // idempotent plutôt que de créer deux commandes distinctes.
            'checkout_token' => ['required', 'string'],
            'buyer_name' => ['required', 'string', 'max:255'],
            // Format seulement (RFC), pas de vérification DNS/MX — même
            // choix que SaveIdentityRequest (T-031).
            'buyer_email' => ['required', 'email:rfc'],
            'buyer_phone' => ['nullable', 'string', 'max:32'],
            'items' => ['array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
            'donation_amount' => ['nullable', 'numeric', 'min:0'],
            'donation_cause' => ['nullable', 'string', 'max:255'],
        ];
    }
}
