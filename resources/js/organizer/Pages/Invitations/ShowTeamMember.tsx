import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import AuthLayout from '../../Layouts/AuthLayout';

interface Props {
    organizationName: string;
    inviterName: string | null;
    email: string;
    roleLabel: string;
    expired: boolean;
    viewer: 'new-account' | 'existing-account' | 'recipient' | 'other-account';
    acceptUrl: string;
    registerUrl: string;
}

export default function ShowTeamMember({ organizationName, inviterName, email, roleLabel, expired, viewer, acceptUrl, registerUrl }: Props) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const acceptForm = useForm({});
    const registerForm = useForm({ name: '', password: '', password_confirmation: '' });

    function accept(event: FormEvent) {
        event.preventDefault();
        acceptForm.post(acceptUrl);
    }

    function register(event: FormEvent) {
        event.preventDefault();
        registerForm.post(registerUrl, { onFinish: () => registerForm.reset('password', 'password_confirmation') });
    }

    return (
        <AuthLayout title="Invitation à rejoindre l'équipe">
            <Head title="Invitation à rejoindre l'équipe" />

            <p className="text-sm text-ink-soft">
                <strong className="font-medium text-ink">{inviterName ?? organizationName}</strong>
                {inviterName && ` (${organizationName})`} vous invite à rejoindre son organisation, avec le rôle{' '}
                <strong className="font-medium text-ink">{roleLabel}</strong>.
            </p>

            <div className="mt-8">
                {expired ? (
                    <p className="rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">
                        Cette invitation a expiré. Demandez à {inviterName ?? organizationName} de vous la renvoyer.
                    </p>
                ) : viewer === 'recipient' ? (
                    <form onSubmit={accept}>
                        <Button type="submit" disabled={acceptForm.processing}>
                            Accepter l'invitation
                        </Button>
                    </form>
                ) : viewer === 'existing-account' ? (
                    <div className="space-y-4">
                        <p className="text-sm text-ink-soft">
                            Un compte Itaza existe déjà pour <span className="text-ink">{email}</span>. Connectez-vous pour
                            accepter l'invitation.
                        </p>
                        <Link
                            href="/login"
                            className="inline-flex min-h-11 w-full items-center justify-center rounded-pill bg-ink px-8 py-4 text-[14.5px] font-medium text-bg hover:opacity-90"
                        >
                            Se connecter
                        </Link>
                    </div>
                ) : viewer === 'other-account' ? (
                    <div className="space-y-4">
                        <p className="text-sm text-ink-soft">
                            Cette invitation est destinée à <span className="text-ink">{email}</span>, mais vous êtes connecté·e
                            avec un autre compte. Déconnectez-vous, puis rouvrez le lien de l'e-mail.
                        </p>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            className="inline-flex min-h-11 w-full items-center justify-center rounded-pill border border-line px-8 py-4 text-[14.5px] font-medium text-ink hover:border-ink"
                        >
                            Se déconnecter
                        </Link>
                    </div>
                ) : (
                    <form onSubmit={register} className="space-y-6">
                        <p className="text-sm text-ink-soft">
                            Créez votre compte pour <span className="text-ink">{email}</span> : vous arriverez directement dans
                            cette organisation.
                        </p>
                        <InputError message={errors.email} />

                        <div>
                            <InputLabel htmlFor="name">Nom</InputLabel>
                            <TextInput
                                id="name"
                                value={registerForm.data.name}
                                onChange={(e) => registerForm.setData('name', e.target.value)}
                                autoFocus
                                required
                            />
                            <InputError message={registerForm.errors.name} />
                        </div>

                        <div>
                            <InputLabel htmlFor="password">Mot de passe</InputLabel>
                            <TextInput
                                id="password"
                                type="password"
                                value={registerForm.data.password}
                                onChange={(e) => registerForm.setData('password', e.target.value)}
                                required
                            />
                            <InputError message={registerForm.errors.password} />
                        </div>

                        <div>
                            <InputLabel htmlFor="password_confirmation">Confirmer le mot de passe</InputLabel>
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                value={registerForm.data.password_confirmation}
                                onChange={(e) => registerForm.setData('password_confirmation', e.target.value)}
                                required
                            />
                        </div>

                        <Button type="submit" disabled={registerForm.processing}>
                            Créer mon compte et accepter
                        </Button>
                    </form>
                )}
            </div>
        </AuthLayout>
    );
}
