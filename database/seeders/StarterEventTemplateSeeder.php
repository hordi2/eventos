<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Event\Models\EventTemplate;
use App\Domain\Event\Models\EventType;
use App\Domain\Organization\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bibliothèque de modèles communautaire (D11) : sans ce seeder, elle reste
 * vide tant qu'aucun organisateur n'a publié l'un de ses propres événements.
 * Six modèles de démarrage plausibles pour les marchés prioritaires
 * (Afrique francophone puis Europe francophone, §1 du CLAUDE.md).
 *
 * Rejouable sans doublon (slug fixe, firstOrCreate y compris sur les lignes
 * retirées depuis) : utile après un `migrate:fresh` comme en production, où
 * il s'exécute seul (`php artisan db:seed --class=StarterEventTemplateSeeder
 * --force`) — DatabaseSeeder crée un « Test User » et ne doit jamais tourner
 * en production.
 */
final class StarterEventTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'itaza-invitation'],
            ['name' => 'Itaza Invitation', 'allow_editor_financial_access' => false],
        );

        $publisher = User::query()->firstOrCreate(
            ['email' => 'modeles@itaza.test'],
            ['name' => 'Équipe Itaza', 'password' => Str::random(64)],
        );

        foreach ($this->templates() as $template) {
            EventTemplate::withTrashed()->firstOrCreate(
                ['slug' => $template['slug']],
                [
                    'organization_id' => $organization->id,
                    'published_by' => $publisher->id,
                    'name' => $template['name'],
                    'summary' => $template['summary'],
                    'category' => $template['category'],
                    'payload' => $template['payload'],
                    'is_published' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}>
     */
    private function templates(): array
    {
        return [
            $this->wedding(),
            $this->conference(),
            $this->religiousGathering(),
            $this->generalAssembly(),
            $this->birthday(),
            $this->graduation(),
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function wedding(): array
    {
        return [
            'slug' => 'demarrage-mariage',
            'name' => 'Mariage',
            'summary' => 'Confirmation de présence, choix du menu et programme de la journée — à adapter aux vôtres.',
            'category' => EventType::Wedding,
            'payload' => [
                'settings' => ['allow_guest_edit' => true],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    ['key' => 'accompagne', 'type' => 'yes_no', 'label' => 'Viendrez-vous accompagné(e) ?'],
                    [
                        'key' => 'menu',
                        'type' => 'meal_choice',
                        'label' => 'Choix du menu',
                        'is_required' => true,
                        'config' => ['ask_scope' => 'each_attendee'],
                        'options' => [
                            ['label' => 'Poisson'],
                            ['label' => 'Viande'],
                            ['label' => 'Végétarien'],
                        ],
                    ],
                    ['key' => 'allergies', 'type' => 'long_text', 'label' => 'Allergie ou régime particulier (optionnel)'],
                    ['key' => 'message_maries', 'type' => 'long_text', 'label' => 'Un mot pour les mariés (optionnel)'],
                ],
                'blocks' => [
                    ['type' => 'save_the_date', 'title' => 'Ils se marient', 'body' => "Nous nous marions et serions heureux de vous compter parmi nous.\nRépondez avant la date indiquée pour que tout soit prêt."],
                    [
                        'type' => 'program',
                        'title' => 'Déroulé de la journée',
                        'items' => [
                            ['time' => '14h00', 'title' => 'Cérémonie civile', 'description' => 'À la mairie, en petit comité.'],
                            ['time' => '16h00', 'title' => 'Cérémonie religieuse', 'description' => "À l'église."],
                            ['time' => '19h00', 'title' => 'Cocktail et dîner', 'description' => 'Au lieu de réception.'],
                            ['time' => '22h00', 'title' => 'Soirée dansante', 'description' => "Jusqu'au bout de la nuit."],
                        ],
                    ],
                    [
                        'type' => 'details',
                        'title' => 'À savoir',
                        'items' => [
                            ['title' => 'Tenue', 'description' => 'Tenue de soirée, couleurs vives bienvenues.'],
                            ['title' => 'Cadeaux', 'description' => 'Votre présence suffit ; une liste existe pour qui y tient.'],
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Questions fréquentes',
                        'items' => [
                            ['question' => 'Puis-je venir avec mes enfants ?', 'answer' => 'Bien sûr, indiquez-les comme accompagnants.'],
                            ['question' => 'Y a-t-il un parking sur place ?', 'answer' => 'Oui, un parking gratuit est prévu à proximité.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function conference(): array
    {
        return [
            'slug' => 'demarrage-conference-entreprise',
            'name' => "Conférence d'entreprise",
            'summary' => "Inscription avec entreprise et fonction, choix d'ateliers, annuaire des participants.",
            'category' => EventType::Conference,
            'payload' => [
                'settings' => ['allow_waitlist' => true, 'has_attendee_directory' => true],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    ['key' => 'entreprise', 'type' => 'short_text', 'label' => 'Entreprise', 'is_required' => true],
                    ['key' => 'fonction', 'type' => 'short_text', 'label' => 'Fonction'],
                    [
                        'key' => 'ateliers',
                        'type' => 'multiple_choice',
                        'label' => 'Ateliers souhaités',
                        'options' => [
                            ['label' => 'Atelier A — Stratégie'],
                            ['label' => 'Atelier B — Innovation'],
                            ['label' => 'Atelier C — Ressources humaines'],
                        ],
                    ],
                    ['key' => 'consentement_suivi', 'type' => 'consent', 'label' => "J'accepte d'être recontacté après la conférence"],
                ],
                'blocks' => [
                    ['type' => 'text', 'title' => 'Bienvenue', 'body' => "Une journée de partenaires, de conférences et d'ateliers pratiques."],
                    [
                        'type' => 'program',
                        'title' => 'Programme',
                        'showIcons' => true,
                        'items' => [
                            ['time' => '8h30', 'title' => 'Accueil', 'description' => 'Café et badges.'],
                            ['time' => '9h00', 'title' => "Conférence d'ouverture"],
                            ['time' => '10h30', 'title' => 'Ateliers en parallèle'],
                            ['time' => '12h30', 'title' => 'Déjeuner'],
                            ['time' => '17h00', 'title' => 'Clôture et cocktail'],
                        ],
                    ],
                    [
                        'type' => 'details',
                        'title' => 'Pratique',
                        'items' => [
                            ['title' => 'Parking', 'description' => 'Parking visiteurs sur place.'],
                            ['title' => 'Wi-Fi', 'description' => "Identifiants donnés à l'accueil."],
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Questions fréquentes',
                        'items' => [
                            ['question' => 'Les supports des conférences seront-ils partagés ?', 'answer' => 'Oui, par e-mail après la journée.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function religiousGathering(): array
    {
        return [
            'slug' => 'demarrage-culte-assemblee',
            'name' => 'Culte ou assemblée religieuse',
            'summary' => 'Inscription simple, avec programme et besoin de covoiturage.',
            'category' => EventType::Religious,
            'payload' => [
                'settings' => [],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    ['key' => 'paroisse', 'type' => 'short_text', 'label' => "Paroisse ou communauté d'origine"],
                    [
                        'key' => 'qualite',
                        'type' => 'single_choice',
                        'label' => 'Vous venez en tant que',
                        'options' => [
                            ['label' => 'Fidèle'],
                            ['label' => 'Responsable de ministère'],
                            ['label' => 'Invité'],
                        ],
                    ],
                    ['key' => 'covoiturage', 'type' => 'yes_no', 'label' => "Avez-vous besoin d'un covoiturage ?"],
                ],
                'blocks' => [
                    ['type' => 'text', 'title' => 'Bienvenue', 'body' => 'Vous êtes les bienvenus à ce moment de communion.'],
                    [
                        'type' => 'program',
                        'title' => 'Déroulé',
                        'items' => [
                            ['time' => '9h00', 'title' => 'Accueil et louange'],
                            ['time' => '10h00', 'title' => 'Enseignement'],
                            ['time' => '12h00', 'title' => 'Repas partagé'],
                        ],
                    ],
                    ['type' => 'venue', 'title' => 'Lieu'],
                    [
                        'type' => 'faq',
                        'title' => 'Questions fréquentes',
                        'items' => [
                            ['question' => 'Un espace est-il prévu pour les enfants ?', 'answer' => 'Oui, un espace encadré est prévu sur place.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function generalAssembly(): array
    {
        return [
            'slug' => 'demarrage-ag-ong',
            'name' => "Assemblée générale d'ONG",
            'summary' => 'Validation manuelle des inscriptions, qualité du participant et procuration.',
            'category' => EventType::GeneralAssembly,
            'payload' => [
                'settings' => ['requires_approval' => true],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    [
                        'key' => 'qualite',
                        'type' => 'single_choice',
                        'label' => 'Vous participez en tant que',
                        'is_required' => true,
                        'options' => [
                            ['label' => 'Membre'],
                            ['label' => 'Observateur'],
                            ['label' => 'Partenaire'],
                        ],
                    ],
                    ['key' => 'procuration', 'type' => 'yes_no', 'label' => 'Portez-vous une procuration ?'],
                    ['key' => 'nom_mandant', 'type' => 'short_text', 'label' => 'Nom du mandant (si procuration)'],
                ],
                'blocks' => [
                    ['type' => 'text', 'title' => 'Assemblée générale', 'body' => "Votre participation compte pour les décisions de l'association."],
                    [
                        'type' => 'program',
                        'title' => 'Ordre du jour',
                        'items' => [
                            ['time' => '9h00', 'title' => 'Rapport moral et financier'],
                            ['time' => '10h00', 'title' => 'Votes et résolutions'],
                            ['time' => '11h30', 'title' => 'Élection du bureau'],
                        ],
                    ],
                    [
                        'type' => 'details',
                        'title' => 'À savoir',
                        'items' => [
                            ['title' => 'Quorum', 'description' => 'Le quorum est fixé par les statuts.'],
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'title' => 'Questions fréquentes',
                        'items' => [
                            ['question' => 'Puis-je me faire représenter ?', 'answer' => 'Oui, par procuration à un autre membre.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function birthday(): array
    {
        return [
            'slug' => 'demarrage-anniversaire',
            'name' => 'Anniversaire',
            'summary' => 'Confirmation de présence avec accompagnants, programme de la fête et petit mot.',
            'category' => EventType::Birthday,
            'payload' => [
                'settings' => ['allow_guest_edit' => true],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    ['key' => 'message', 'type' => 'long_text', 'label' => 'Un petit mot (optionnel)'],
                ],
                'blocks' => [
                    ['type' => 'save_the_date', 'title' => 'On fête ça !', 'body' => 'Venez célébrer avec nous, confirmez votre présence.'],
                    [
                        'type' => 'program',
                        'title' => 'Déroulé',
                        'items' => [
                            ['time' => '18h00', 'title' => 'Accueil'],
                            ['time' => '19h00', 'title' => 'Dîner'],
                            ['time' => '21h00', 'title' => 'Gâteau et musique'],
                        ],
                    ],
                    [
                        'type' => 'details',
                        'title' => 'À savoir',
                        'items' => [
                            ['title' => 'Cadeaux', 'description' => 'Votre présence est le plus beau des cadeaux.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{slug: string, name: string, summary: string, category: EventType, payload: array<string, mixed>}
     */
    private function graduation(): array
    {
        return [
            'slug' => 'demarrage-remise-diplomes',
            'name' => 'Remise de diplômes',
            'summary' => 'Confirmation de présence des familles et amis, programme de la cérémonie.',
            'category' => EventType::Graduation,
            'payload' => [
                'settings' => [],
                'fields' => [
                    ['key' => 'nom_complet', 'type' => 'short_text', 'label' => 'Nom complet', 'is_required' => true],
                    [
                        'key' => 'lien',
                        'type' => 'single_choice',
                        'label' => 'Vous êtes',
                        'options' => [
                            ['label' => 'Diplômé(e)'],
                            ['label' => 'Membre de la famille'],
                            ['label' => 'Ami(e)'],
                        ],
                    ],
                ],
                'blocks' => [
                    ['type' => 'text', 'title' => 'Félicitations !', 'body' => 'Venez célébrer la réussite de nos diplômés.'],
                    [
                        'type' => 'program',
                        'title' => 'Déroulé de la cérémonie',
                        'items' => [
                            ['time' => '15h00', 'title' => 'Accueil des invités'],
                            ['time' => '15h30', 'title' => 'Discours et remise des diplômes'],
                            ['time' => '17h00', 'title' => 'Photos et cocktail'],
                        ],
                    ],
                    [
                        'type' => 'details',
                        'title' => 'À savoir',
                        'items' => [
                            ['title' => 'Places assises', 'description' => 'Places limitées, confirmez votre présence au plus tôt.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
