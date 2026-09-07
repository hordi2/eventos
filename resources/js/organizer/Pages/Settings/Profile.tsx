import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface Props {
    user: {
        name: string;
        email: string;
        email_verified: boolean;
    };
    isSoleOrganizationOwner: boolean;
}

const STATUS_MESSAGES: Record<string, string> = {
    'profile-updated': 'Profil mis à jour.',
    'password-updated': 'Mot de passe mis à jour.',
    'verification-link-sent': 'Un e-mail de vérification a été envoyé.',
};

export default function Profile({ user, isSoleOrganizationOwner }: Props) {
    const { flash } = usePage<SharedProps>().props;

    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });
    const deleteForm = useForm({ password: '' });

    function submitProfile(event: FormEvent) {
        event.preventDefault();
        profileForm.patch('/settings/profile', { preserveScroll: true });
    }

    function submitPassword(event: FormEvent) {
        event.preventDefault();
        passwordForm.put('/settings/profile/password', {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    }

    function submitDelete(event: FormEvent) {
        event.preventDefault();

        if (!confirm('Supprimer définitivement votre compte ? Cette action est irréversible.')) {
            return;
        }

        deleteForm.delete('/settings/profile');
    }

    return (
        <SettingsLayout title="Paramètres" active="profile">
            <Head title="Compte" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">Profil</h2>
                <p className="mb-6 text-sm text-ink-soft">Votre nom et votre adresse e-mail de connexion.</p>

                <form onSubmit={submitProfile} className="max-w-md">
                    <div className="mb-5">
                        <InputLabel htmlFor="name">Nom</InputLabel>
                        <TextInput
                            id="name"
                            value={profileForm.data.name}
                            onChange={(e) => profileForm.setData('name', e.target.value)}
                        />
                        <InputError message={profileForm.errors.name} />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="email">E-mail</InputLabel>
                        <TextInput
                            id="email"
                            type="email"
                            value={profileForm.data.email}
                            onChange={(e) => profileForm.setData('email', e.target.value)}
                        />
                        <InputError message={profileForm.errors.email} />
                        {!user.email_verified && (
                            <p className="mt-2 text-sm text-ink-soft">
                                Adresse non vérifiée.{' '}
                                <button
                                    type="button"
                                    onClick={() => router.post('/email/verification-notification')}
                                    className="underline underline-offset-2"
                                >
                                    Renvoyer le lien de vérification
                                </button>
                            </p>
                        )}
                    </div>

                    <Button type="submit" disabled={profileForm.processing}>
                        Enregistrer
                    </Button>
                </form>
            </section>

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">Mot de passe</h2>
                <p className="mb-6 text-sm text-ink-soft">Choisissez un mot de passe que vous n'utilisez sur aucun autre site.</p>

                <form onSubmit={submitPassword} className="max-w-md">
                    <div className="mb-5">
                        <InputLabel htmlFor="current_password">Mot de passe actuel</InputLabel>
                        <TextInput
                            id="current_password"
                            type="password"
                            value={passwordForm.data.current_password}
                            onChange={(e) => passwordForm.setData('current_password', e.target.value)}
                        />
                        <InputError message={passwordForm.errors.current_password} />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="password">Nouveau mot de passe</InputLabel>
                        <TextInput
                            id="password"
                            type="password"
                            value={passwordForm.data.password}
                            onChange={(e) => passwordForm.setData('password', e.target.value)}
                        />
                        <InputError message={passwordForm.errors.password} />
                    </div>

                    <div className="mb-5">
                        <InputLabel htmlFor="password_confirmation">Confirmer le mot de passe</InputLabel>
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            value={passwordForm.data.password_confirmation}
                            onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                        />
                    </div>

                    <Button type="submit" disabled={passwordForm.processing}>
                        Mettre à jour le mot de passe
                    </Button>
                </form>
            </section>

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">Session</h2>
                <p className="mb-6 text-sm text-ink-soft">Se déconnecter de cet appareil.</p>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    className="inline-flex min-h-11 items-center rounded-pill border border-line px-8 text-[14.5px] text-ink hover:border-ink"
                >
                    Se déconnecter
                </Link>
            </section>

            <section className="rounded-card border border-danger/30 bg-danger-bg p-6">
                <h2 className="mb-1 font-serif text-xl italic text-danger">Zone de danger</h2>
                <p className="mb-6 text-sm text-ink-soft">La suppression de votre compte est définitive et anonymise vos informations personnelles.</p>

                {isSoleOrganizationOwner ? (
                    <p className="text-sm text-danger">
                        Vous êtes seul(e) propriétaire d'au moins une organisation. Transférez la propriété à un autre membre ou supprimez
                        cette organisation avant de pouvoir supprimer votre compte.
                    </p>
                ) : (
                    <form onSubmit={submitDelete} className="max-w-md">
                        <div className="mb-5">
                            <InputLabel htmlFor="delete_password">Confirmez avec votre mot de passe</InputLabel>
                            <TextInput
                                id="delete_password"
                                type="password"
                                value={deleteForm.data.password}
                                onChange={(e) => deleteForm.setData('password', e.target.value)}
                            />
                            <InputError message={deleteForm.errors.password} />
                        </div>
                        <Button type="submit" variant="danger" disabled={deleteForm.processing}>
                            Supprimer définitivement mon compte
                        </Button>
                    </form>
                )}
            </section>
        </SettingsLayout>
    );
}
