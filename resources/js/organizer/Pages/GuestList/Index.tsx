import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InviteeFormModal from '../../Components/GuestList/InviteeFormModal';
import SenderAgreementModal from '../../Components/GuestList/SenderAgreementModal';
import { type InviteeRow, type InviteeTag } from '../../Components/GuestList/types';
import TextInput from '../../Components/TextInput';
import EventLayout from '../../Layouts/EventLayout';

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface GuestListPageProps {
    event: { id: number; title: string };
    invitees: Paginated<InviteeRow>;
    stats: { invitees: number; groups: number; responded: number };
    groups: string[];
    tags: InviteeTag[];
    search: string;
    canEdit: boolean;
    needsAgreement: boolean;
    maxCompanions: number;
}

const RESPONSE_VARIANTS: Record<string, 'neutral' | 'success' | 'danger'> = {
    confirmed: 'success',
    waitlisted: 'neutral',
    declined: 'neutral',
    cancelled: 'danger',
};

const HEADER_CELL = 'px-4 py-3 font-normal';

function EmptyIllustration() {
    return (
        <div aria-hidden="true" className="relative mx-auto h-36 w-44">
            <div className="absolute inset-x-6 top-6 bottom-0 rotate-[-4deg] rounded-card bg-bg-deep" />
            <div className="absolute inset-x-3 top-3 bottom-3 space-y-3 rounded-card border border-line bg-bg p-4 shadow-sm">
                {[0.9, 0.7, 0.8].map((width, index) => (
                    <div key={index} className="flex items-center gap-2">
                        <span className="h-4 w-4 shrink-0 rounded-full bg-bg-deep" />
                        <span className="h-2 rounded-pill bg-bg-deep" style={{ width: `${width * 100}%` }} />
                    </div>
                ))}
            </div>
            <span className="absolute -top-1 -right-1 flex h-9 w-9 items-center justify-center rounded-full bg-accent text-lg text-bg shadow">+</span>
        </div>
    );
}

export default function Index({ event, invitees, stats, groups, tags, search, canEdit, needsAgreement, maxCompanions }: GuestListPageProps) {
    const [agreementOpen, setAgreementOpen] = useState(needsAgreement);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<InviteeRow | null>(null);
    const [query, setQuery] = useState(search);
    const listUrl = `/events/${event.id}/guest-list`;
    const isEmpty = stats.invitees === 0 && search === '';

    function openForm(invitee: InviteeRow | null) {
        if (invitee === null && needsAgreement) {
            setAgreementOpen(true);

            return;
        }

        setEditing(invitee);
        setFormOpen(true);
    }

    function openImport() {
        if (needsAgreement) {
            setAgreementOpen(true);

            return;
        }

        router.visit(`${listUrl}/import`);
    }

    function remove(invitee: InviteeRow) {
        if (window.confirm(`Retirer ${invitee.fullName} de la liste ? Sa fiche contact et ses éventuelles réponses sont conservées.`)) {
            router.delete(`${listUrl}/${invitee.id}`, { preserveScroll: true });
        }
    }

    function submitSearch(formEvent: FormEvent) {
        formEvent.preventDefault();
        router.get(listUrl, query.trim() === '' ? {} : { q: query.trim() }, { preserveState: true, replace: true });
    }

    const actions = canEdit && (
        <div className="flex flex-col gap-3 sm:flex-row">
            <Button type="button" onClick={openImport} className="sm:w-auto">
                Importer une liste
            </Button>
            <Button type="button" variant="secondary" onClick={() => openForm(null)} className="sm:w-auto">
                Ajouter un invité
            </Button>
        </div>
    );

    return (
        <EventLayout title="Liste des invités" wide>
            <Head title={`Liste des invités — ${event.title}`} />

            <div className="mb-8 flex flex-wrap items-end justify-between gap-6">
                <p className="max-w-2xl text-sm text-ink-soft">
                    Les personnes que vous invitez à cet événement, leurs groupes et ce que l'invitation leur accorde. Chaque réponse se met à jour
                    dès qu'un invité répond.
                </p>
                {!isEmpty && actions}
            </div>

            {needsAgreement && !agreementOpen && (
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-card bg-bg p-4 text-sm text-ink ring-1 ring-line">
                    <span>Pour ajouter ou importer des invités, acceptez d'abord les engagements d'envoi de votre organisation.</span>
                    <button type="button" onClick={() => setAgreementOpen(true)} className="font-medium underline hover:no-underline">
                        Lire et accepter
                    </button>
                </div>
            )}

            {isEmpty ? (
                <section className="rounded-card border border-line bg-bg px-6 py-14 text-center">
                    <EmptyIllustration />
                    <h2 className="mt-8 font-serif text-2xl text-ink italic">Aucun invité pour l'instant</h2>
                    <p className="mx-auto mt-3 max-w-md text-sm text-ink-soft">
                        La liste d'invités est facultative. Elle sert à envoyer vos invitations par e-mail ou WhatsApp, et à réserver l'événement
                        aux personnes que vous avez invitées.
                    </p>
                    {canEdit && <div className="mt-8 flex justify-center">{actions}</div>}
                </section>
            ) : (
                <>
                    <dl className="mb-6 grid grid-cols-3 gap-3 sm:max-w-xl">
                        {[
                            ['Invités', stats.invitees],
                            ['Groupes', stats.groups],
                            ['Ont répondu', stats.responded],
                        ].map(([label, value]) => (
                            <div key={label} className="rounded-card border border-line bg-bg px-4 py-3">
                                <dt className="font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">{label}</dt>
                                <dd className="mt-1 text-2xl text-ink tabular-nums">{value}</dd>
                            </div>
                        ))}
                    </dl>

                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <form onSubmit={submitSearch} className="flex w-full gap-2 sm:w-auto" role="search">
                            <TextInput
                                type="search"
                                value={query}
                                placeholder="Nom, e-mail ou groupe"
                                aria-label="Rechercher un invité"
                                onChange={(e) => setQuery(e.target.value)}
                                className="sm:w-72"
                            />
                            <button type="submit" className="shrink-0 rounded-pill border border-line px-4 text-sm text-ink hover:border-ink">
                                Rechercher
                            </button>
                        </form>
                        <Link href={`/events/${event.id}/segments`} className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase hover:text-ink">
                            Segments de réponses
                        </Link>
                    </div>

                    {invitees.data.length === 0 ? (
                        <p className="rounded-card border border-line bg-bg px-4 py-6 text-sm text-ink-soft">Aucun invité ne correspond à « {search} ».</p>
                    ) : (
                        <div className="overflow-x-auto rounded-card border border-line bg-bg">
                            <table className="w-full min-w-[820px] text-left text-sm">
                                <thead className="border-b border-line font-label text-[11px] tracking-[0.12em] text-ink-soft uppercase">
                                    <tr>
                                        <th scope="col" className={HEADER_CELL}>
                                            Invité
                                        </th>
                                        <th scope="col" className={HEADER_CELL}>
                                            Groupe
                                        </th>
                                        <th scope="col" className={HEADER_CELL}>
                                            Accompagnants
                                        </th>
                                        <th scope="col" className={HEADER_CELL}>
                                            Tags
                                        </th>
                                        <th scope="col" className={HEADER_CELL}>
                                            Réponse
                                        </th>
                                        {canEdit && (
                                            <th scope="col" className={HEADER_CELL}>
                                                <span className="sr-only">Actions</span>
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody>
                                    {invitees.data.map((invitee) => (
                                        <tr key={invitee.id} className="border-b border-line align-top last:border-0">
                                            <td className={`border-l-2 px-4 py-3 ${invitee.groupKey ? 'border-accent' : 'border-transparent'}`}>
                                                <p className="text-ink">{invitee.fullName}</p>
                                                <p className="text-xs text-ink-soft">{[invitee.email, invitee.phone].filter(Boolean).join(' · ') || '—'}</p>
                                                {invitee.ccEmail && <p className="text-xs text-ink-soft">Copie : {invitee.ccEmail}</p>}
                                            </td>
                                            <td className="px-4 py-3 text-ink-soft">{invitee.groupKey ?? '—'}</td>
                                            <td className="px-4 py-3 text-ink-soft tabular-nums">{invitee.companionsLabel}</td>
                                            <td className="px-4 py-3">
                                                {invitee.tags.length === 0 ? (
                                                    <span className="text-ink-soft">—</span>
                                                ) : (
                                                    <ul className="flex flex-wrap gap-1.5">
                                                        {invitee.tags.map((tag) => (
                                                            <li key={tag.name} className="inline-flex items-center gap-1.5 rounded-pill bg-bg-alt px-2.5 py-0.5 text-xs text-ink">
                                                                <span aria-hidden="true" className="h-2 w-2 rounded-full" style={{ backgroundColor: tag.color }} />
                                                                {tag.name}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {invitee.response ? (
                                                    <Badge variant={RESPONSE_VARIANTS[invitee.response.status] ?? 'neutral'}>{invitee.response.label}</Badge>
                                                ) : (
                                                    <span className="text-xs text-ink-soft">Pas encore répondu</span>
                                                )}
                                            </td>
                                            {canEdit && (
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <button type="button" onClick={() => openForm(invitee)} className="text-sm text-ink hover:underline">
                                                        Modifier
                                                    </button>
                                                    <button type="button" onClick={() => remove(invitee)} className="ml-4 text-sm text-danger hover:underline">
                                                        Retirer
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {invitees.last_page > 1 && (
                        <nav aria-label="Pages de la liste" className="mt-6 flex flex-wrap gap-2">
                            {invitees.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={`rounded-pill px-3.5 py-1.5 text-sm ${link.active ? 'bg-ink text-bg' : 'text-ink-soft hover:text-ink'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    )}
                </>
            )}

            {formOpen && (
                <InviteeFormModal
                    key={editing?.id ?? 'nouveau'}
                    open={formOpen}
                    onClose={() => setFormOpen(false)}
                    eventId={event.id}
                    invitee={editing}
                    groups={groups}
                    tags={tags}
                    maxCompanions={maxCompanions}
                />
            )}

            {needsAgreement && <SenderAgreementModal open={agreementOpen} onClose={() => setAgreementOpen(false)} />}
        </EventLayout>
    );
}
