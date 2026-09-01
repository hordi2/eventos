import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/email/verification-notification');
    }

    return (
        <AuthLayout title="Vérifie ton adresse e-mail">
            <Head title="Vérification de l'e-mail" />

            <p className="mb-4 text-sm text-gray-600">
                Merci de ton inscription ! Avant de commencer, peux-tu vérifier ton adresse e-mail en cliquant sur le
                lien qu'on vient de t'envoyer ? Si tu ne l'as pas reçu, on peut t'en renvoyer un autre.
            </p>

            {status === 'verification-link-sent' && (
                <p className="mb-4 text-sm font-medium text-green-600">
                    Un nouveau lien de vérification vient d'être envoyé à l'adresse fournie lors de l'inscription.
                </p>
            )}

            <form onSubmit={submit} className="flex items-center justify-between">
                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    Renvoyer l'e-mail de vérification
                </button>

                <Link href="/logout" method="post" as="button" className="text-sm text-gray-600 hover:text-gray-900">
                    Se déconnecter
                </Link>
            </form>
        </AuthLayout>
    );
}
