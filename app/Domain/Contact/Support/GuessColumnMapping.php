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
    ];

    /**
     * @param  list<string>  $headers
     * @return array<string, string|null> en-tête => champ Contact deviné (ou null)
     */
    public function handle(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $normalized = $this->normalize($header);

            // Deux passes : une correspondance exacte et plus spécifique
            // (« consentement e-mail ») doit toujours l'emporter sur une
            // correspondance mot à mot plus large (« e-mail » contient le
            // mot « mail », mais ce n'est pas ce qu'on cherche ici) — sans
            // quoi l'ordre des champs dans SYNONYMS déciderait au hasard
            // entre deux champs également valides.
            $mapping[$header] = $this->exactMatch($normalized) ?? $this->wordMatch($this->words($normalized));
        }

        return $mapping;
    }

    private function exactMatch(string $normalized): ?string
    {
        foreach (self::SYNONYMS as $field => $synonyms) {
            if (in_array($normalized, $synonyms, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $words
     */
    private function wordMatch(array $words): ?string
    {
        foreach (self::SYNONYMS as $field => $synonyms) {
            foreach ($synonyms as $synonym) {
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
