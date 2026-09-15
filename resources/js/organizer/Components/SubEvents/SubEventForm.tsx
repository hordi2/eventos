import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Checkbox from '../Checkbox';
import InputError from '../InputError';
import InputLabel from '../InputLabel';
import TextInput from '../TextInput';
import { type SubEventRow } from './types';

interface SubEventFormProps {
    eventId: number;
    subEvent?: SubEventRow;
    onDone?: () => void;
}

interface SubEventDraft {
    title: string;
    start_at: string;
    end_at: string;
    capacity: string;
    allow_waitlist: boolean;
}

/**
 * Création ou modification d'une session. Les dates se saisissent dans le
 * fuseau de l'événement principal ; une capacité vide veut dire illimitée.
 */
export default function SubEventForm({ eventId, subEvent, onDone }: SubEventFormProps) {
    const form = useForm<SubEventDraft>({
        title: subEvent?.title ?? '',
        start_at: subEvent?.startAt ?? '',
        end_at: subEvent?.endAt ?? '',
        capacity: subEvent?.capacity != null ? String(subEvent.capacity) : '',
        allow_waitlist: subEvent?.allowWaitlist ?? false,
    });
    const prefix = subEvent ? `sub_event_${subEvent.id}` : 'sub_event_new';

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.transform((data) => ({ ...data, capacity: data.capacity === '' ? null : Number(data.capacity) }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!subEvent) {
                    form.reset();
                }

                onDone?.();
            },
        };

        if (subEvent) {
            form.patch(`/events/${eventId}/sub-events/${subEvent.id}`, options);
        } else {
            form.post(`/events/${eventId}/sub-events`, options);
        }
    }

    return (
        <form onSubmit={submit} className="space-y-5">
            <div>
                <InputLabel htmlFor={`${prefix}_title`}>Nom de la session</InputLabel>
                <TextInput
                    id={`${prefix}_title`}
                    type="text"
                    value={form.data.title}
                    maxLength={255}
                    placeholder="Dîner de gala, atelier photo, cérémonie religieuse…"
                    onChange={(changeEvent) => form.setData('title', changeEvent.target.value)}
                />
                <InputError message={form.errors.title} />
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
                <div>
                    <InputLabel htmlFor={`${prefix}_start`}>Début</InputLabel>
                    <TextInput
                        id={`${prefix}_start`}
                        type="datetime-local"
                        value={form.data.start_at}
                        onChange={(changeEvent) => form.setData('start_at', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.start_at} />
                </div>
                <div>
                    <InputLabel htmlFor={`${prefix}_end`}>Fin</InputLabel>
                    <TextInput
                        id={`${prefix}_end`}
                        type="datetime-local"
                        value={form.data.end_at}
                        onChange={(changeEvent) => form.setData('end_at', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.end_at} />
                </div>
            </div>

            <div className="grid gap-5 sm:grid-cols-2 sm:items-end">
                <div>
                    <InputLabel htmlFor={`${prefix}_capacity`}>Capacité (personnes)</InputLabel>
                    <TextInput
                        id={`${prefix}_capacity`}
                        type="number"
                        min={1}
                        placeholder="Illimitée"
                        value={form.data.capacity}
                        onChange={(changeEvent) => form.setData('capacity', changeEvent.target.value)}
                    />
                    <InputError message={form.errors.capacity} />
                </div>
                <label className="flex items-center gap-2 pb-3 text-sm text-ink">
                    <Checkbox checked={form.data.allow_waitlist} onChange={(changeEvent) => form.setData('allow_waitlist', changeEvent.target.checked)} />
                    Liste d'attente quand la session est complète
                </label>
            </div>

            <div className="flex flex-wrap gap-3">
                <button
                    type="submit"
                    disabled={form.processing}
                    className="inline-flex min-h-10 items-center rounded-pill bg-ink px-6 py-2 text-sm font-medium text-bg transition hover:opacity-90 disabled:opacity-50"
                >
                    {subEvent ? 'Enregistrer la session' : 'Ajouter la session'}
                </button>
                {onDone && subEvent && (
                    <button type="button" onClick={onDone} className="inline-flex min-h-10 items-center rounded-pill border border-line px-6 py-2 text-sm font-medium text-ink hover:border-ink">
                        Annuler
                    </button>
                )}
            </div>
        </form>
    );
}
