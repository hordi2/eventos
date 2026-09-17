<?php

declare(strict_types=1);

namespace App\Http\Requests\Organizer\Export;

use App\Domain\Analytics\Models\ExportType;
use App\Domain\Event\Models\Event;
use App\Support\Export\BuildExportRows;
use App\Support\Segments\EventSegment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExportRequest extends FormRequest
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
        $type = ExportType::tryFrom((string) $this->input('type'));
        // Les colonnes des questions du formulaire dépendent de l'événement
        // (BuildExportRows::columns) : sans lui, elles seraient refusées.
        $event = Event::query()->find($this->route('event'));
        $validColumns = $type !== null ? array_keys(app(BuildExportRows::class)->columns($type, $event)) : [];

        return [
            'type' => ['required', Rule::enum(ExportType::class)],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', Rule::in($validColumns)],
            // Le segment n'a de sens que pour le type "contacts" (voir
            // BuildExportRows) : le rejeter explicitement pour les autres
            // types plutôt que de le laisser silencieusement ignoré évite
            // à l'organisateur de croire à tort qu'un filtre a été appliqué.
            'segment' => [$type === ExportType::Contacts ? 'nullable' : 'prohibited', Rule::enum(EventSegment::class)],
        ];
    }
}
