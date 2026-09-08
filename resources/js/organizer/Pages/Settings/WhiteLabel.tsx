import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface Props {
    emailFromName: string | null;
    emailReplyTo: string | null;
    defaultFromAddress: string;
}

const STATUS_MESSAGES: Record<string, string> = {
    'white-label-updated': 'Étiquetage blanc enregistré.',
};

export default function WhiteLabel({ emailFromName, emailReplyTo, defaultFromAddress }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const { data, setData, patch, processing, errors } = useForm({
        email_from_name: emailFromName ?? '',
        email_reply_to: emailReplyTo ?? '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        patch('/settings/white-label', { preserveScroll: true });
    }

    return (
        <SettingsLayout title="Paramètres" active="white-label">
            <Head title="Étiquetage blanc" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <h2 className="mb-6 border-b border-line pb-4 text-xl">Étiquetage blanc par e-mail</h2>

            <div className="mb-10 flex flex-wrap items-start gap-6 rounded-card bg-bg-alt p-6 ring-1 ring-line">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent/10 text-accent">
                    <svg viewBox="0 0 24 24" className="h-5 w-5 stroke-current fill-none" strokeWidth="1.6" strokeLinejoin="round">
                        <path d="m12 3 2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8L3.5 9.2l5.9-.9L12 3Z" />
                    </svg>
                </span>

                <div className="min-w-64 flex-1">
                    <p className="mb-1 font-medium text-ink">
                        Envoyer depuis votre propre domaine demande un accompagnement.
                    </p>
                    <p className="text-sm text-ink-soft">
                        L'adresse technique d'expédition reste celle d'Itaza : la changer sans authentification de
                        domaine (SPF/DKIM) dégraderait la délivrabilité de vos invitations au lieu de l'améliorer.
                        Écrivez-nous pour étudier la mise en place sur votre domaine.
                    </p>
                </div>

                <Link
                    href="/support"
                    className="shrink-0 rounded-pill border border-line px-6 py-2.5 text-sm font-medium text-ink hover:border-ink"
                >
                    Nous contacter
                </Link>
            </div>

            <div className="mb-10 flex flex-col items-center border-b border-line pb-10 text-center">
                <svg
                    viewBox="0 0 24 24"
                    className="mb-6 h-24 w-24 stroke-ink-soft fill-none"
                    strokeWidth="1"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                >
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <path d="m3 7 9 6 9-6" />
                    <circle cx="18.5" cy="17.5" r="3.5" fill="var(--color-bg)" />
                    <path d="M18.5 16v3M17 17.5h3" />
                </svg>
                <p className="mb-1 text-ink">
                    L'authentification de domaine <strong>n'est pas activée</strong> sur votre compte.
                </p>
                <p className="text-sm text-ink-soft">
                    Les e-mails que vous envoyez partent de <strong>{defaultFromAddress}</strong>.
                </p>
            </div>

            <section className="max-w-md">
                <h3 className="mb-1 font-serif text-lg text-ink italic">Personnaliser malgré tout l'expéditeur</h3>
                <p className="mb-6 text-sm text-ink-soft">
                    Sans authentification de domaine, vous pouvez déjà choisir le nom affiché et l'adresse à laquelle
                    vos invités répondent.
                </p>

                <form onSubmit={submit}>
                    <div className="mb-5">
                        <InputLabel htmlFor="email_from_name">Nom de l'expéditeur</InputLabel>
                        <TextInput
                            id="email_from_name"
                            value={data.email_from_name}
                            onChange={(e) => setData('email_from_name', e.target.value)}
                            placeholder="Gala Annuel Itaza"
                        />
                        <InputError message={errors.email_from_name} />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="email_reply_to">Adresse de réponse</InputLabel>
                        <TextInput
                            id="email_reply_to"
                            type="email"
                            value={data.email_reply_to}
                            onChange={(e) => setData('email_reply_to', e.target.value)}
                            placeholder="contact@votre-organisation.com"
                        />
                        <InputError message={errors.email_reply_to} />
                    </div>

                    <Button type="submit" disabled={processing}>
                        Enregistrer
                    </Button>
                </form>
            </section>
        </SettingsLayout>
    );
}
