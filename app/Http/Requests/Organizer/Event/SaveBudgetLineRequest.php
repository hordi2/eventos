<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Event;

use App\Domain\Event\Models\BudgetCategory;
use App\Domain\Event\Models\BudgetLineKind;
use App\Domain\Event\Models\Event;
use App\Support\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

final class SaveBudgetLineRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(BudgetLineKind::class)],
            'category' => ['required', Rule::enum(BudgetCategory::class)],
            'label' => ['required', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            // Montants saisis tels qu'on les écrit (« 1 500 », « 12,50 ») :
            // Money::parse les convertit en entier sans jamais passer par un
            // float (règle 4.2).
            'planned' => ['required', 'string', 'max:20', $this->amount()],
            'actual' => ['nullable', 'string', 'max:20', $this->amount()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Donnez un nom à ce poste.',
            'planned.required' => 'Indiquez le montant prévu.',
        ];
    }

    /**
     * Montant lisible dans la devise de l'événement, posée par le
     * contrôleur : un montant plus précis que la devise est refusé plutôt
     * qu'arrondi en silence.
     */
    private function amount(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            try {
                Money::parse($value, $this->currency());
            } catch (InvalidArgumentException) {
                $fail('Ce montant est illisible : écrivez-le en chiffres, par exemple 1500 ou 1500,50.');
            }
        };
    }

    /**
     * Devise de l'événement : toutes les lignes de son budget la partagent,
     * sans quoi les totaux n'auraient aucun sens (règle 4.2).
     */
    public function currency(): string
    {
        return (string) Event::query()->whereKey($this->route('event'))->value('currency');
    }
}
