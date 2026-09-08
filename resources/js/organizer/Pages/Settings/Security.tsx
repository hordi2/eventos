import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import Toggle from '../../Components/Toggle';
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
                <h2 className="mb-4 border-b border-line pb-4 text-xl">Authentification multifactorielle (MFA)</h2>

                <div className="flex items-start gap-4 py-2">
                    <Toggle
                        checked={mfaEmailEnabled}
                        onChange={toggleMfa}
                        label="Exiger un code MFA par e-mail lors de la connexion"
                    />
                    <span className="text-sm text-ink">Exiger un code MFA par e-mail lors de la connexion</span>
                </div>

                {organizationMfa && (
                    <div className="flex items-start gap-4 py-2">
                        <Toggle
                            checked={organizationMfa.requireMfaForMembers}
                            onChange={toggleOrganizationMfa}
                            label="Exiger la MFA par e-mail pour tous les membres de l'organisation"
                        />
                        <span className="text-sm text-ink">
                            Exiger la MFA par e-mail pour tous les membres de l'organisation
                        </span>
                    </div>
                )}

                <p className="mt-4 text-sm text-ink-soft">
                    Un code à usage unique est envoyé par e-mail à chaque connexion, en plus du mot de passe.
                </p>
            </section>

            <section className="mb-14">
                <h2 className="mb-6 border-b border-line pb-4 text-xl">Mot de passe</h2>

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
