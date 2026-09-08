import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface Props {
    mfaEmailEnabled: boolean;
    organizationMfa: { requireMfaForMembers: boolean } | null;
}

const STATUS_MESSAGES: Record<string, string> = {
    'password-updated': 'Mot de passe mis à jour.',
    'mfa-updated': 'Préférence de vérification en deux étapes enregistrée.',
    'organization-mfa-updated': 'Politique de sécurité de l’organisation mise à jour.',
};

export default function Security({ mfaEmailEnabled, organizationMfa }: Props) {
    const { flash } = usePage<SharedProps>().props;

    const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

    function submitPassword(event: FormEvent) {
        event.preventDefault();
        passwordForm.put('/settings/security/password', {
            preserveScroll: true,
            onSuccess: () => passwordForm.reset(),
        });
    }

    function toggleMfa(checked: boolean) {
        router.patch('/settings/security/mfa', { mfa_email_enabled: checked }, { preserveScroll: true });
    }

    function toggleOrganizationMfa(checked: boolean) {
        router.patch('/settings/security/organization-mfa', { require_mfa_for_members: checked }, { preserveScroll: true });
    }

    return (
        <SettingsLayout title="Paramètres" active="security">
            <Head title="Sécurité" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">Authentification multifactorielle (MFA)</h2>
                <p className="mb-6 text-sm text-ink-soft">
                    Un code à usage unique vous est envoyé par e-mail à chaque connexion, en plus de votre mot de passe.
                </p>

                <label className="flex max-w-md items-center justify-between rounded-card border border-line p-4 text-sm">
                    <span>Exiger un code MFA par e-mail lors de la connexion</span>
                    <input
                        type="checkbox"
                        checked={mfaEmailEnabled}
                        onChange={(e) => toggleMfa(e.target.checked)}
                        className="h-5 w-5 rounded border-line text-ink focus:ring-ink"
                    />
                </label>

                {organizationMfa && (
                    <label className="mt-3 flex max-w-md items-center justify-between rounded-card border border-line p-4 text-sm">
                        <span>Exiger la MFA par e-mail pour tous les membres de l'organisation</span>
                        <input
                            type="checkbox"
                            checked={organizationMfa.requireMfaForMembers}
                            onChange={(e) => toggleOrganizationMfa(e.target.checked)}
                            className="h-5 w-5 rounded border-line text-ink focus:ring-ink"
                        />
                    </label>
                )}
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
        </SettingsLayout>
    );
}
