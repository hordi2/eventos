import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import Button from '../../Components/Button';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/email/verification-notification');
    }

    return (
        <AuthLayout title="Vérifie ton adresse e-mail">
            <Head title="Vérification de l'e-mail" />

            <p className="mb-6 text-sm text-ink-soft">
                Merci de ton inscription ! Avant de commencer, peux-tu vérifier ton adresse e-mail en cliquant sur le
                lien qu'on vient de t'envoyer ? Si tu ne l'as pas reçu, on peut t'en renvoyer un autre.
            </p>

            {status === 'verification-link-sent' && (
                <p className="mb-6 text-sm font-medium text-ink">
                    Un nouveau lien de vérification vient d'être envoyé à l'adresse fournie lors de l'inscription.
                </p>
            )}

            <form onSubmit={submit} className="space-y-4">
                <Button type="submit" disabled={processing}>
                    Renvoyer l'e-mail de vérification
                </Button>

                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="block w-full text-center text-sm text-ink-soft underline underline-offset-4 hover:text-ink"
                >
                    Se déconnecter
                </Link>
            </form>
        </AuthLayout>
    );
}
