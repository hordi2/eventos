import { Head, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import PrimaryButton from '../../Components/PrimaryButton';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/reset-password', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout title="Nouveau mot de passe">
            <Head title="Réinitialisation du mot de passe" />

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="email">E-mail</InputLabel>
                    <TextInput
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="password">Nouveau mot de passe</InputLabel>
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.password} />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation">Confirmer le mot de passe</InputLabel>
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <PrimaryButton type="submit" disabled={processing}>
                    Réinitialiser le mot de passe
                </PrimaryButton>
            </form>
        </AuthLayout>
    );
}
