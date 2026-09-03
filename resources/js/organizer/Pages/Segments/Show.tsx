import { Head, Link, router, useForm } from '@inertiajs/react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import Select from '../../Components/Select';
import Table from '../../Components/Table';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface AttendeeInfo {
    attendee_id: number;
    checked_in_at: string | null;
}

interface ContactRow {
    id: number;
    full_name: string;
    email: string | null;
    phone_e164: string | null;
    attendee: AttendeeInfo | null;
}

interface TagOption {
    id: number;
    name: string;
    color: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export default function Show({
    event,
    segment,
    contacts,
    tags,
    canApplyTag,
    canCheckIn,
}: {
    event: { id: number; title: string };
    segment: { value: string; label: string };
    contacts: Paginated<ContactRow>;
    tags: TagOption[];
    canApplyTag: boolean;
    canCheckIn: boolean;
}) {
    const { data, setData, post, processing } = useForm({ tag_id: tags[0]?.id ?? '' });

    function applyTag() {
        if (!data.tag_id) {
            return;
        }

        if (confirm(`Appliquer ce tag aux ${contacts.total} contact(s) de ce segment ?`)) {
            post(`/events/${event.id}/segments/${segment.value}/tag`);
        }
    }

    function toggleCheckIn(attendeeId: number) {
        router.post(`/attendees/${attendeeId}/toggle-check-in`);
    }

    return (
        <OrganizerLayout title={segment.label} eyebrow={event.title}>
            <Head title={`${segment.label} — ${event.title}`} />

            {canApplyTag && tags.length > 0 && (
                <div className="mb-8 flex flex-wrap items-center gap-3 rounded-card border border-line bg-bg p-4">
                    <span className="font-label text-xs tracking-[0.1em] text-ink-soft uppercase">Appliquer un tag au segment</span>
                    <Select value={data.tag_id} onChange={(e) => setData('tag_id', Number(e.target.value))} className="max-w-xs">
                        {tags.map((tag) => (
                            <option key={tag.id} value={tag.id}>
                                {tag.name}
                            </option>
                        ))}
                    </Select>
                    <Button variant="secondary" className="w-auto" onClick={applyTag} disabled={processing}>
                        Appliquer à tout le segment ({contacts.total})
                    </Button>
                </div>
            )}

            <Table
                rowKey={(contact) => contact.id}
                emptyMessage="Aucun contact dans ce segment pour l'instant."
                columns={[
                    {
                        key: 'name',
                        header: 'Nom',
                        render: (contact) => (
                            <Link href={`/contacts/${contact.id}/edit`} className="text-ink underline-offset-2 hover:underline">
                                {contact.full_name}
                            </Link>
                        ),
                    },
                    { key: 'email', header: 'E-mail', render: (contact) => contact.email ?? '—' },
                    { key: 'phone', header: 'Téléphone', render: (contact) => contact.phone_e164 ?? '—' },
                    {
                        key: 'presence',
                        header: 'Présence',
                        render: (contact) => {
                            if (!contact.attendee) {
                                return '—';
                            }

                            const present = contact.attendee.checked_in_at !== null;

                            if (!canCheckIn) {
                                return <Badge variant={present ? 'success' : 'neutral'}>{present ? 'Présent' : 'Non pointé'}</Badge>;
                            }

                            return (
                                <button
                                    type="button"
                                    onClick={() => toggleCheckIn(contact.attendee!.attendee_id)}
                                    className="inline-flex"
                                >
                                    <Badge variant={present ? 'success' : 'neutral'}>{present ? 'Présent — annuler' : 'Marquer présent'}</Badge>
                                </button>
                            );
                        },
                    },
                ]}
                rows={contacts.data}
            />

            {contacts.last_page > 1 && (
                <nav className="mt-6 flex gap-2">
                    {contacts.links.map((link, index) => (
                        <Link
                            key={index}
                            href={link.url ?? '#'}
                            className={`rounded-pill px-3.5 py-1.5 text-sm ${
                                link.active ? 'bg-ink text-bg' : 'text-ink-soft hover:text-ink'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </OrganizerLayout>
    );
}
