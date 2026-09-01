import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import InputError from '../../Components/InputError';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/forgot-password');
    }

    return (
        <AuthLayout title="Mot de passe oublié">
            <Head title="Mot de passe oublié" />

            <p className="mb-4 text-sm text-gray-600">
                Indique ton adresse e-mail, on t'envoie un lien pour choisir un nouveau mot de passe.
            </p>

            {status && <p className="mb-4 text-sm font-medium text-green-600">{status}</p>}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                        E-mail
                    </label>
                    <input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        autoFocus
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                >
                    Envoyer le lien de réinitialisation
                </button>
            </form>
        </AuthLayout>
    );
}
