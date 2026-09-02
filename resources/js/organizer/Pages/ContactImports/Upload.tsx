import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

export default function Upload() {
    const { setData, post, processing, errors } = useForm<{ file: File | null }>({ file: null });

    function handleSubmit(e: FormEvent) {
        e.preventDefault();
        post('/contact-imports', { forceFormData: true });
    }

    return (
        <OrganizerLayout
            title="Importer des contacts"
            eyebrow="Contacts"
            nav={
                <Link href="/contacts" className="font-label text-xs tracking-[0.14em] text-ink-soft uppercase hover:text-ink">
                    Tous les contacts
                </Link>
            }
        >
            <Head title="Importer des contacts" />

            <div className="max-w-lg">
                <p className="mb-8 text-sm text-ink-soft">
                    Fichier CSV avec une ligne d'en-têtes. Vous choisirez ensuite à quel champ correspond chaque colonne.
                </p>

                <form onSubmit={handleSubmit}>
                    <div className="mb-8">
                        <InputLabel htmlFor="file">Fichier CSV</InputLabel>
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                            className="block w-full text-sm text-ink file:mr-4 file:rounded-pill file:border-0 file:bg-ink file:px-4 file:py-2 file:text-sm file:font-medium file:text-bg"
                        />
                        <InputError message={errors.file} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Continuer
                    </Button>
                </form>
            </div>
        </OrganizerLayout>
    );
}
