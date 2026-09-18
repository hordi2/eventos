<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

/**
 * Mappage automatique proposé à partir des en-têtes (T-041 : « mappage
 * proposé automatiquement d'après les en-têtes ») — l'organisateur reste
 * libre de le corriger avant de valider l'import.
 */
final class GuessColumnMapping
{
    /**
     * @var array<string, list<string>>
     */
    private const SYNONYMS = [
        'first_name' => ['prenom', 'prénom', 'first name', 'firstname', 'given name'],
        'last_name' => ['nom', 'nom de famille', 'last name', 'lastname', 'surname', 'family name'],
        'email_consent' => ['consentement email', 'consentement e-mail', 'email consent', 'opt-in email'],
        'sms_consent' => ['consentement sms', 'sms consent', 'opt-in sms'],
        'whatsapp_consent' => ['consentement whatsapp', 'whatsapp consent', 'opt-in whatsapp', 'whatsapp'],
        'email' => ['email', 'e-mail', 'mail', 'courriel', 'adresse email'],
        'phone_e164' => ['telephone', 'téléphone', 'tel', 'phone', 'phone number', 'mobile', 'gsm'],
        'company' => ['entreprise', 'societe', 'société', 'company', 'organisation'],
        'job_title' => ['fonction', 'poste', 'job title', 'title', 'role'],
        'preferred_language' => ['langue', 'language', 'langue preferee', 'langue préférée'],
        'household_name' => ['foyer', 'groupe', 'household', 'family', 'famille'],
        'tags' => ['tags', 'tag', 'etiquettes', 'etiquette'],
    ];

    /**
     * Colonnes propres à la liste d'invités d'un événement, en tête de
     * recherche : « Groupe » y désigne les invités qui répondent ensemble,
     * pas le foyer. Les en-têtes du modèle RSVPify sont reconnus, pour
     * faciliter une migration.
     *
     * @var array<string, list<string>>
     */
    private const GUEST_LIST_SYNONYMS = [
        'group_key' => ['groupe', 'group', 'group id', 'id de groupe', 'numero de groupe'],
        'companions_allowed' => [
            'accompagnants', 'accompagnants autorises', 'invites supplementaires', '+1', 'plus one',
            'additional guests allowed', "additional guest(s) allowed (+1's)",
        ],
        'cc_email' => ['e-mail en copie', 'email en copie', 'copie e-mail', 'copie email', 'cc', 'cc email', 'cc email recipient'],
    ];

    /**
     * Un champ n'est proposé qu'une fois : la première colonne qui lui
     * correspond le prend. Sans cela, « CC EMAIL RECIPIENT » viendrait
     * écraser la vraie colonne e-mail par correspondance mot à mot.
     *
     * @param  list<string>  $headers
     * @return array<string, string|null> en-tête => champ Contact deviné (ou null)
     */
    public function handle(array $headers, bool $forGuestList = false): array
    {
        $synonyms = $forGuestList ? $this->guestListSynonyms() : self::SYNONYMS;
        $mapping = [];
        $taken = [];

        foreach ($headers as $header) {
            $normalized = $this->normalize($header);

            // Deux passes : une correspondance exacte et plus spécifique
            // (« consentement e-mail ») doit toujours l'emporter sur une
            // correspondance mot à mot plus large (« e-mail » contient le
            // mot « mail », mais ce n'est pas ce qu'on cherche ici) — sans
            // quoi l'ordre des champs dans SYNONYMS déciderait au hasard
            // entre deux champs également valides.
            $field = $this->exactMatch($synonyms, $normalized) ?? $this->wordMatch($synonyms, $this->words($normalized));
            $mapping[$header] = $field !== null && ! in_array($field, $taken, true) ? $field : null;

            if ($mapping[$header] !== null) {
                $taken[] = $mapping[$header];
            }
        }

        return $mapping;
    }

    /**
     * @return array<string, list<string>>
     */
    private function guestListSynonyms(): array
    {
        $base = self::SYNONYMS;
        $base['household_name'] = array_values(array_diff($base['household_name'], ['groupe']));

        return self::GUEST_LIST_SYNONYMS + $base;
    }

    /**
     * @param  array<string, list<string>>  $synonyms
     */
    private function exactMatch(array $synonyms, string $normalized): ?string
    {
        foreach ($synonyms as $field => $candidates) {
            if (in_array($normalized, $candidates, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $synonyms
     * @param  list<string>  $words
     */
    private function wordMatch(array $synonyms, array $words): ?string
    {
        foreach ($synonyms as $field => $candidates) {
            foreach ($candidates as $synonym) {
                if (! str_contains($synonym, ' ') && in_array($synonym, $words, true)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c']);
    }

    /**
     * Les espaces/barres/virgules séparent des mots distincts, mais pas le
     * trait d'union : « e-mail » doit rester un seul mot, sans quoi il se
     * scinderait en « e » + « mail » et « mail » matcherait à tort le champ
     * email plutôt que email_consent pour un en-tête « Consentement e-mail ».
     *
     * @return list<string>
     */
    private function words(string $normalized): array
    {
        return preg_split('/[\s\/_,;]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
