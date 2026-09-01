import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import PrimaryButton from '../../Components/PrimaryButton';

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

            <p className="mb-6 text-sm text-ink-soft">
                Indique ton adresse e-mail, on t'envoie un lien pour choisir un nouveau mot de passe.
            </p>

            {status && <p className="mb-6 text-sm font-medium text-ink">{status}</p>}

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="email">E-mail</InputLabel>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                <PrimaryButton type="submit" disabled={processing}>
                    Envoyer le lien de réinitialisation
                </PrimaryButton>
            </form>
        </AuthLayout>
    );
}
