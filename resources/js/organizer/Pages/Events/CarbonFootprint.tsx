import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface ModeRow {
    mode: string;
    label: string;
    people: number;
    kilometres: number;
    kilograms: number;
}

interface Props {
    event: { id: number; title: string; mealsServed: number | null; printedPages: number | null; enabled: boolean };
    footprint: {
        travel: number;
        meals: number;
        print: number;
        total: number;
        perAttendee: number;
        attendeeCount: number;
        declaredCount: number;
        declarationRate: number;
        byMode: ModeRow[];
        carpoolOffers: number;
        carpoolSeekers: number;
        carpoolSaving: number;
    };
}

const NUMBER = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });

/**
 * Empreinte carbone d'un événement (D12) : ce que pèsent les déplacements
 * déclarés, les repas et les impressions, et le rapport à joindre à un
 * appel d'offres.
 */
export default function CarbonFootprint({ event, footprint }: Props) {
    const form = useForm({
        meals_served: event.mealsServed ?? '',
        printed_pages: event.printedPages ?? '',
    });

    function submit(submitEvent: FormEvent) {
        submitEvent.preventDefault();
        form.patch(`/events/${event.id}/empreinte`);
    }

    return (
        <EventLayout title="Empreinte carbone" eyebrow={event.title}>
            <Head title="Empreinte carbone" />

            {!event.enabled && (
                <p className="mb-8 rounded-card bg-bg px-5 py-4 text-sm text-ink-soft ring-1 ring-line">
                    La déclaration de déplacement n'est pas ouverte : vos participants ne peuvent pas encore dire comment
                    ils viennent. Activez-la dans les réglages de l'événement.
                </p>
            )}

            <div className="mb-8 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Total', value: `${NUMBER.format(footprint.total)} kg` },
                    { label: 'Par participant', value: `${NUMBER.format(footprint.perAttendee)} kg` },
                    { label: 'Ont déclaré', value: `${footprint.declarationRate} %` },
                ].map((tile) => (
                    <div key={tile.label} className="rounded-card bg-bg p-5 ring-1 ring-line">
                        <p className="font-label text-[0.62rem] tracking-[0.2em] text-ink-soft uppercase">{tile.label}</p>
                        <p className="mt-2 font-serif text-2xl text-ink tabular-nums">{tile.value}</p>
                    </div>
                ))}
            </div>

            <p className="mb-8 text-sm text-ink-soft">
                {footprint.declaredCount} participants sur {footprint.attendeeCount} ont dit comment ils venaient. Le total
                ne vaut que pour eux : plus ils sont nombreux à répondre, plus le bilan est juste.
            </p>

            <h2 className="mb-3 font-serif text-lg italic">D'où vient cette empreinte</h2>
            <ul className="mb-8 space-y-2">
                {[
                    { label: 'Déplacements déclarés', value: footprint.travel },
                    { label: 'Repas servis', value: footprint.meals },
                    { label: 'Impressions', value: footprint.print },
                ].map((row) => (
                    <li key={row.label} className="flex items-center justify-between rounded-card bg-bg px-5 py-3 ring-1 ring-line">
                        <span className="text-ink">{row.label}</span>
                        <span className="tabular-nums text-ink">{NUMBER.format(row.value)} kg</span>
                    </li>
                ))}
            </ul>

            {footprint.byMode.length > 0 && (
                <>
                    <h2 className="mb-3 font-serif text-lg italic">Comment ils sont venus</h2>
                    <ul className="mb-8 space-y-2">
                        {footprint.byMode.map((row) => (
                            <li key={row.mode} className="flex flex-wrap items-center justify-between gap-3 rounded-card bg-bg px-5 py-3 ring-1 ring-line">
                                <span className="text-ink">{row.label}</span>
                                <span className="text-sm text-ink-soft tabular-nums">
                                    {row.people} personnes · {NUMBER.format(row.kilometres)} km ·{' '}
                                    <span className="text-ink">{NUMBER.format(row.kilograms)} kg</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            )}

            {footprint.carpoolSaving > 0 && (
                <p className="mb-8 rounded-card bg-bg-alt px-5 py-4 text-sm text-ink-soft">
                    Si tous ceux qui viennent seuls en voiture partageaient leur trajet, l'événement pèserait{' '}
                    <span className="text-ink">{NUMBER.format(footprint.carpoolSaving)} kg</span> de moins.{' '}
                    {footprint.carpoolOffers} proposent des places, {footprint.carpoolSeekers} en cherchent une.
                </p>
            )}

            <form onSubmit={submit} className="mb-8 rounded-card bg-bg p-5 ring-1 ring-line">
                <h2 className="mb-1 font-serif text-lg italic">Ce que vous seul savez</h2>
                <p className="mb-5 text-sm text-ink-soft">
                    Itaza ne peut pas deviner ces deux nombres : ils viennent de votre traiteur et de votre imprimeur.
                </p>

                <div className="grid gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="meals_served">Repas servis</InputLabel>
                        <TextInput
                            id="meals_served"
                            type="number"
                            min={0}
                            value={String(form.data.meals_served)}
                            onChange={(changeEvent) => form.setData('meals_served', changeEvent.target.value)}
                        />
                    </div>
                    <div>
                        <InputLabel htmlFor="printed_pages">Pages imprimées</InputLabel>
                        <TextInput
                            id="printed_pages"
                            type="number"
                            min={0}
                            value={String(form.data.printed_pages)}
                            onChange={(changeEvent) => form.setData('printed_pages', changeEvent.target.value)}
                        />
                        <p className="mt-1.5 text-xs text-ink-soft">Badges, programmes, signalétique.</p>
                    </div>
                </div>

                <Button type="submit" disabled={form.processing} className="mt-5 w-auto px-6 py-2">
                    {form.processing ? 'Enregistrement…' : 'Enregistrer'}
                </Button>
            </form>

            <a
                href={`/events/${event.id}/empreinte.pdf`}
                className="inline-flex min-h-11 items-center rounded-pill border border-line px-5 py-2.5 text-sm text-ink hover:border-ink"
            >
                Télécharger le rapport
            </a>
        </EventLayout>
    );
}
