import { Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import Badge from '../../Components/Badge';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Modal from '../../Components/Modal';
import Table from '../../Components/Table';
import TextInput from '../../Components/TextInput';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface TagRow {
    id: number;
    name: string;
    color: string;
    contacts_count: number;
}

function TagForm({ tag, onDone }: { tag: TagRow | null; onDone: () => void }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: tag?.name ?? '',
        color: tag?.color ?? '#1a6e42',
    });

    function submit(e: FormEvent) {
        e.preventDefault();

        if (tag) {
            patch(`/tags/${tag.id}`, { onSuccess: onDone });
        } else {
            post('/tags', { onSuccess: onDone });
        }
    }

    return (
        <form onSubmit={submit}>
            <div className="mb-5">
                <InputLabel htmlFor="name">Nom</InputLabel>
                <TextInput id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                <InputError message={errors.name} />
            </div>

            <div className="mb-8">
                <InputLabel htmlFor="color">Couleur</InputLabel>
                <input
                    id="color"
                    type="color"
                    value={data.color}
                    onChange={(e) => setData('color', e.target.value)}
                    className="h-11 w-20 cursor-pointer rounded-control border border-line bg-transparent"
                />
                <InputError message={errors.color} />
            </div>

            <Button type="submit" disabled={processing}>
                {tag ? 'Enregistrer' : 'Créer le tag'}
            </Button>
        </form>
    );
}

export default function Index({ tags }: { tags: TagRow[] }) {
    const [editing, setEditing] = useState<TagRow | null | undefined>(undefined);

    function destroy(tag: TagRow) {
        if (confirm(`Supprimer le tag « ${tag.name} » ? Il sera retiré de ${tag.contacts_count} contact(s).`)) {
            router.delete(`/tags/${tag.id}`);
        }
    }

    return (
        <OrganizerLayout title="Tags" eyebrow="Contacts">
            <Head title="Tags" />

            <div className="mb-6 flex justify-end">
                <Button className="w-auto" onClick={() => setEditing(null)}>
                    Créer un tag
                </Button>
            </div>

            <Table
                rowKey={(tag) => tag.id}
                emptyMessage="Aucun tag pour l'instant."
                columns={[
                    {
                        key: 'name',
                        header: 'Nom',
                        render: (tag) => (
                            <span className="inline-flex items-center gap-2">
                                <span className="h-3 w-3 rounded-full" style={{ backgroundColor: tag.color }} />
                                {tag.name}
                            </span>
                        ),
                    },
                    { key: 'contacts', header: 'Contacts', render: (tag) => <Badge>{tag.contacts_count}</Badge> },
                    {
                        key: 'actions',
                        header: '',
                        render: (tag) => (
                            <div className="flex justify-end gap-4">
                                <button type="button" onClick={() => setEditing(tag)} className="text-sm text-ink-soft hover:text-ink">
                                    Modifier
                                </button>
                                <button type="button" onClick={() => destroy(tag)} className="text-sm text-danger hover:opacity-80">
                                    Supprimer
                                </button>
                            </div>
                        ),
                    },
                ]}
                rows={tags}
            />

            <Modal open={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? 'Modifier le tag' : 'Créer un tag'}>
                <TagForm tag={editing ?? null} onDone={() => setEditing(undefined)} />
            </Modal>
        </OrganizerLayout>
    );
}
