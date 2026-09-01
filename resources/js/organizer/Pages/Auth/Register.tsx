import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import PrimaryButton from '../../Components/PrimaryButton';
import GoogleButton from '../../Components/GoogleButton';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        organization_name: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/register', {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthLayout title="Créer un compte">
            <Head title="Inscription" />

            <form onSubmit={submit} className="space-y-6">
                <div>
                    <InputLabel htmlFor="name">Nom</InputLabel>
                    <TextInput
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        autoFocus
                        required
                    />
                    <InputError message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="organization_name">Nom de l'organisation</InputLabel>
                    <TextInput
                        id="organization_name"
                        type="text"
                        value={data.organization_name}
                        onChange={(e) => setData('organization_name', e.target.value)}
                        required
                    />
                    <InputError message={errors.organization_name} />
                </div>

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
                    <InputLabel htmlFor="password">Mot de passe</InputLabel>
                    <TextInput
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
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
                    Créer mon compte
                </PrimaryButton>

                <GoogleButton />

                <p className="text-center text-sm text-ink-soft">
                    Déjà un compte ?{' '}
                    <Link href="/login" className="font-medium text-ink underline underline-offset-4">
                        Se connecter
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
