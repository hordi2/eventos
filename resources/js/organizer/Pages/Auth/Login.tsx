import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import AuthLayout from '../../Layouts/AuthLayout';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import Button from '../../Components/Button';
import GoogleButton from '../../Components/GoogleButton';

export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <AuthLayout title="Se connecter">
            <Head title="Connexion" />

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

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 font-sans text-sm text-ink-soft">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-line text-ink focus:ring-ink"
                        />
                        Se souvenir de moi
                    </label>

                    <Link href="/forgot-password" className="text-sm text-ink-soft underline underline-offset-4 hover:text-ink">
                        Mot de passe oublié ?
                    </Link>
                </div>

                <Button type="submit" disabled={processing}>
                    Se connecter
                </Button>

                <GoogleButton />

                <p className="text-center text-sm text-ink-soft">
                    Pas encore de compte ?{' '}
                    <Link href="/register" className="font-medium text-ink underline underline-offset-4">
                        S'inscrire
                    </Link>
                </p>
            </form>
        </AuthLayout>
    );
}
