import { Head } from '@inertiajs/react';
import OrganizerLayout from '../../Layouts/OrganizerLayout';

interface Activity {
    finalite: string;
    donnees: string;
    base_legale: string;
    destinataires: string;
    conservation: string;
}

interface Props {
    activities: Activity[];
}

export default function Register({ activities }: Props) {
    return (
        <OrganizerLayout title="Registre des traitements" eyebrow="Organisation">
            <Head title="Registre des traitements" />

            <div className="mb-8 flex items-center justify-between">
                <p className="max-w-2xl text-ink-soft">
                    Document exigé par l'article 30 du RGPD : liste des traitements de données personnelles effectués par la plateforme,
                    leur finalité, leur base légale et leur durée de conservation.
                </p>
                <a href="/compliance/register/pdf" className="shrink-0 text-sm text-accent underline underline-offset-2">
                    Télécharger en PDF
                </a>
            </div>

            <div className="overflow-x-auto rounded-card ring-1 ring-line">
                <table className="w-full text-left text-sm">
                    <thead className="bg-bg-deep text-xs text-ink-soft uppercase">
                        <tr>
                            <th className="p-4">Finalité</th>
                            <th className="p-4">Données traitées</th>
                            <th className="p-4">Base légale</th>
                            <th className="p-4">Destinataires</th>
                            <th className="p-4">Conservation</th>
                        </tr>
                    </thead>
                    <tbody>
                        {activities.map((activity) => (
                            <tr key={activity.finalite} className="border-t border-line align-top">
                                <td className="p-4 font-medium">{activity.finalite}</td>
                                <td className="p-4 text-ink-soft">{activity.donnees}</td>
                                <td className="p-4 text-ink-soft">{activity.base_legale}</td>
                                <td className="p-4 text-ink-soft">{activity.destinataires}</td>
                                <td className="p-4 text-ink-soft">{activity.conservation}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </OrganizerLayout>
    );
}
