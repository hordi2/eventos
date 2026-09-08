import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface UsageMetric {
    used: number;
    quota: number | null;
}

interface Props {
    user: {
        name: string;
        email: string;
        email_verified: boolean;
    };
    isSoleOrganizationOwner: boolean;
    plan: {
        label: string;
        usage: {
            registrations: UsageMetric;
            emails: UsageMetric;
            active_events: UsageMetric;
        };
    } | null;
}

const STATUS_MESSAGES: Record<string, string> = {
    'profile-updated': 'Profil mis à jour.',
    'verification-link-sent': 'Un e-mail de vérification a été envoyé.',
};

const METRIC_LABELS: Record<string, string> = {
    registrations: 'Inscriptions ce mois-ci',
    emails: 'E-mails ce mois-ci',
    active_events: 'Événements actifs',
};

function formatQuota(quota: number | null): string {
    return quota === null ? 'illimité' : String(quota);
}

export default function Profile({ user, isSoleOrganizationOwner, plan }: Props) {
    const { flash } = usePage<SharedProps>().props;

    const profileForm = useForm({ name: user.name, email: user.email });
    const deleteForm = useForm({ password: '' });

    function submitProfile(event: FormEvent) {
        event.preventDefault();
        profileForm.patch('/settings/profile', { preserveScroll: true });
    }

    function submitDelete(event: FormEvent) {
        event.preventDefault();

        if (!confirm('Supprimer définitivement votre compte ? Cette action est irréversible.')) {
            return;
        }

        deleteForm.delete('/settings/profile');
    }

    const metrics: Array<{ key: 'registrations' | 'emails' | 'active_events'; metric: UsageMetric }> = plan
        ? [
              { key: 'registrations', metric: plan.usage.registrations },
              { key: 'emails', metric: plan.usage.emails },
              { key: 'active_events', metric: plan.usage.active_events },
          ]
        : [];

    return (
        <SettingsLayout title="Paramètres" active="profile">
            <Head title="Mon compte" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}

            <section className="mb-14">
                <h2 className="mb-1 font-serif text-xl italic">À propos de vous</h2>
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

            {plan && (
                <section className="mb-14 rounded-card bg-bg p-6 ring-1 ring-line">
                    <div className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="font-serif text-xl italic">Plan actuel</h2>
                            <p className="text-sm text-ink-soft">{plan.label}</p>
                        </div>
                        <Link href="/billing" className="text-sm text-ink underline underline-offset-4">
                            Voir les forfaits et les tarifs
                        </Link>
                    </div>

                    <div className="space-y-4">
                        {metrics.map(({ key, metric }) => (
                            <div key={key}>
                                <div className="mb-1 flex items-center justify-between text-sm">
                                    <span>{METRIC_LABELS[key]}</span>
                                    <span className="text-ink-soft">
                                        {metric.used} / {formatQuota(metric.quota)}
                                    </span>
                                </div>
                                {metric.quota !== null && (
                                    <div className="h-2 w-full overflow-hidden rounded-full bg-bg-deep">
                                        <div
                                            className="h-full rounded-full bg-ink"
                                            style={{ width: `${Math.min(100, (metric.used / metric.quota) * 100)}%` }}
                                        />
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </section>
            )}

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
