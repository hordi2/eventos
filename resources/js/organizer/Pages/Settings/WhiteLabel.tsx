import { Head, useForm, usePage } from '@inertiajs/react';
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
}

const STATUS_MESSAGES: Record<string, string> = {
    'white-label-updated': 'Étiquetage blanc enregistré.',
};

export default function WhiteLabel({ emailFromName, emailReplyTo }: Props) {
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

            <section className="max-w-md">
                <h2 className="mb-1 font-serif text-xl italic">Étiquetage blanc par e-mail</h2>
                <p className="mb-6 text-sm text-ink-soft">
                    Personnalisez le nom affiché et l'adresse de réponse des e-mails envoyés à vos invités. L'adresse
                    technique d'envoi reste celle d'Itaza — une vérification de domaine complète (SPF/DKIM) nécessite un
                    accompagnement dédié, contactez le support pour en discuter.
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
