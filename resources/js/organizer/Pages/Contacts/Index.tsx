import { Head, Link, router } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Button from '../../Components/Button';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface ContactRow {
    id: number;
    full_name: string;
    email: string | null;
    phone_e164: string | null;
    household_name: string | null;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    links: { url: string | null; label: string; active: boolean }[];
}

export default function Index({ contacts, search }: { contacts: Paginated<ContactRow>; search: string }) {
    const [query, setQuery] = useState(search);

    function submit(e: FormEvent) {
        e.preventDefault();
        router.get('/contacts', query ? { q: query } : {}, { preserveState: true });
    }

    return (
        <OrganizerLayout title="Contacts" eyebrow="Invités">
            <Head title="Contacts" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <form onSubmit={submit} className="flex max-w-sm flex-1 gap-2">
                    <TextInput
                        type="search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Rechercher un nom ou un e-mail…"
                    />
                    <Button type="submit" variant="secondary" className="w-auto shrink-0">
                        Rechercher
                    </Button>
                </form>

                <div className="flex gap-3">
                    <Link
                        href="/contact-imports/create"
                        className="inline-flex min-h-11 items-center gap-2.5 rounded-pill border border-line px-6 py-3 font-sans text-sm font-medium text-ink hover:border-ink"
                    >
                        Importer un fichier
                    </Link>
                    <Link
                        href="/contacts/create"
                        className="inline-flex min-h-11 items-center gap-2.5 rounded-pill bg-ink px-6 py-3 font-sans text-sm font-medium text-bg"
                    >
                        Ajouter un contact
                    </Link>
                </div>
            </div>

            <Table
                rowKey={(contact) => contact.id}
                emptyMessage="Aucun contact pour l'instant."
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
                    { key: 'household', header: 'Foyer', render: (contact) => contact.household_name ?? '—' },
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
