import { Head, router, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import Button from '../../Components/Button';
import InputError from '../../Components/InputError';
import InputLabel from '../../Components/InputLabel';
import Select from '../../Components/Select';
import TextInput from '../../Components/TextInput';
import SettingsLayout from '../../Layouts/SettingsLayout';
import { type SharedProps } from '../../types';

interface RoleOption {
    value: string;
    label: string;
}

interface TeamMember {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface PendingInvitation {
    id: number;
    email: string;
    role: string;
    status: 'pending' | 'expired';
}

interface Props {
    members: TeamMember[];
    invitations: PendingInvitation[];
    roleOptions: RoleOption[];
}

const STATUS_MESSAGES: Record<string, string> = {
    'team-member-invited': 'Invitation envoyée.',
    'team-member-role-updated': 'Rôle mis à jour.',
    'team-member-removed': "Membre retiré : il n'a plus accès à votre organisation.",
    'team-member-invitation-resent': 'Invitation renvoyée : le lien du précédent e-mail ne fonctionne plus.',
    'team-member-invitation-cancelled': 'Invitation annulée.',
};

export default function Team({ members, invitations, roleOptions }: Props) {
    const { flash, errors } = usePage<SharedProps & { errors: Record<string, string> }>().props;
    const { data, setData, post, processing, errors: formErrors, reset } = useForm({
        email: '',
        role: roleOptions[0]?.value ?? '',
    });

    function invite(event: FormEvent) {
        event.preventDefault();
        post('/settings/team', { preserveScroll: true, onSuccess: () => reset('email') });
    }

    function updateRole(member: TeamMember, role: string) {
        router.patch(`/settings/team/${member.id}`, { role }, { preserveScroll: true });
    }

    function removeMember(member: TeamMember) {
        if (confirm(`Retirer ${member.name} ? Cette personne perdra immédiatement l'accès à votre organisation.`)) {
            router.delete(`/settings/team/${member.id}`, { preserveScroll: true });
        }
    }

    function resendInvitation(invitation: PendingInvitation) {
        router.post(`/settings/team/invitations/${invitation.id}/resend`, {}, { preserveScroll: true });
    }

    function cancelInvitation(invitation: PendingInvitation) {
        router.delete(`/settings/team/invitations/${invitation.id}`, { preserveScroll: true });
    }

    return (
        <SettingsLayout title="Paramètres" active="team">
            <Head title="Équipe" />

            {flash.status && STATUS_MESSAGES[flash.status] && (
                <div className="mb-8 rounded-card bg-success-bg p-4 text-sm text-success ring-1 ring-success/30">
                    {STATUS_MESSAGES[flash.status]}
                </div>
            )}
            {errors.membership && (
                <div className="mb-8 rounded-card bg-danger-bg p-4 text-sm text-danger ring-1 ring-danger/30">{errors.membership}</div>
            )}

            <section className="mb-10 rounded-card border border-line bg-bg p-6">
                <h2 className="mb-1 font-medium text-ink">Inviter un membre</h2>
                <p className="mb-6 text-sm text-ink-soft">
                    La personne reçoit un e-mail avec un lien pour rejoindre votre organisation, avec le rôle choisi.
                </p>

                <form onSubmit={invite} className="flex flex-wrap items-start gap-4">
                    <div className="min-w-[220px] flex-1">
                        <InputLabel htmlFor="invite-email">E-mail</InputLabel>
                        <TextInput
                            id="invite-email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={formErrors.email} />
                    </div>

                    <div className="w-48">
                        <InputLabel htmlFor="invite-role">Rôle</InputLabel>
                        <Select id="invite-role" value={data.role} onChange={(e) => setData('role', e.target.value)}>
                            {roleOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </Select>
                        <InputError message={formErrors.role} />
                    </div>

                    <Button type="submit" disabled={processing} className="mt-6">
                        Inviter
                    </Button>
                </form>
            </section>

            <section className="mb-10 rounded-card border border-line bg-bg">
                <div className="border-b border-line px-6 py-5">
                    <p className="text-sm text-ink-soft">
                        {members.length} membre{members.length > 1 ? 's' : ''}
                    </p>
                </div>

                <ul className="divide-y divide-line">
                    {members.map((member) => (
                        <li key={member.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                            <div className="min-w-0">
                                <p className="truncate font-medium text-ink">{member.name}</p>
                                <p className="truncate text-sm text-ink-soft">{member.email}</p>
                            </div>

                            {member.role === 'owner' ? (
                                <span className="rounded-pill bg-bg-alt px-3 py-1 text-xs text-ink-soft">Propriétaire</span>
                            ) : (
                                <div className="flex items-center gap-4">
                                    <Select
                                        value={member.role}
                                        onChange={(e) => updateRole(member, e.target.value)}
                                        className="w-44"
                                    >
                                        {roleOptions.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </Select>
                                    <button
                                        type="button"
                                        onClick={() => removeMember(member)}
                                        className="text-sm text-danger underline underline-offset-4"
                                    >
                                        Retirer
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </section>

            {invitations.length > 0 && (
                <section className="rounded-card border border-line bg-bg">
                    <div className="border-b border-line px-6 py-5">
                        <p className="text-sm text-ink-soft">Invitations en attente</p>
                    </div>

                    <ul className="divide-y divide-line">
                        {invitations.map((invitation) => (
                            <li key={invitation.id} className="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                                <div className="min-w-0">
                                    <p className="flex flex-wrap items-center gap-2">
                                        <span className="truncate font-medium text-ink">{invitation.email}</span>
                                        <span
                                            className={`rounded-pill px-2.5 py-0.5 text-xs ${
                                                invitation.status === 'expired'
                                                    ? 'bg-danger-bg text-danger'
                                                    : 'bg-bg-alt text-ink-soft'
                                            }`}
                                        >
                                            {invitation.status === 'expired' ? 'Invitation expirée' : 'Invitation envoyée'}
                                        </span>
                                    </p>
                                    <p className="text-sm text-ink-soft">
                                        {roleOptions.find((option) => option.value === invitation.role)?.label ?? invitation.role}
                                    </p>
                                </div>

                                <div className="flex shrink-0 gap-5 text-sm">
                                    <button
                                        type="button"
                                        onClick={() => resendInvitation(invitation)}
                                        className="text-ink underline underline-offset-4"
                                    >
                                        Renvoyer
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => cancelInvitation(invitation)}
                                        className="text-danger underline underline-offset-4"
                                    >
                                        Annuler
                                    </button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </SettingsLayout>
    );
}
