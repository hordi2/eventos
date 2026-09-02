<?php

declare(strict_types=1);

namespace App\Domain\Contact\Actions;

use App\Domain\Contact\Models\Contact;
use App\Domain\Contact\Models\ContactImport;
use App\Domain\Contact\Models\ContactImportRow;
use App\Domain\Contact\Models\ContactImportRowStatus;
use App\Domain\Contact\Models\DuplicateStrategy;
use App\Domain\Contact\Support\CalculateContactSimilarity;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Traite une ligne de la feuille importée (T-041). Toujours appelée depuis
 * ProcessContactImportJob, jamais directement — une ligne à la fois, pour
 * que 10 000 lignes se traitent en file d'attente sans jamais bloquer
 * l'interface (critère d'acceptation du ticket).
 */
final class ImportContactRow
{
    public function __construct(
        private readonly CalculateContactSimilarity $calculateContactSimilarity,
        private readonly FindOrCreateHousehold $findOrCreateHousehold,
    ) {}

    /**
     * @param  array<string, string>  $rawRow  en-tête => valeur brute
     */
    public function handle(ContactImport $import, int $rowNumber, array $rawRow): ContactImportRow
    {
        $data = $this->extractMappedFields($import->column_mapping ?? [], $rawRow);

        if (($data['email'] ?? '') === '' && ($data['first_name'] ?? '') === '' && ($data['last_name'] ?? '') === '') {
            return $this->recordRow($import, $rowNumber, $rawRow, ContactImportRowStatus::Rejected, "Ligne vide : aucune colonne reconnue n'a de valeur.");
        }

        if (($data['email'] ?? '') !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->recordRow($import, $rowNumber, $rawRow, ContactImportRowStatus::Rejected, "Adresse e-mail invalide : \"{$data['email']}\".");
        }

        if (($data['phone_e164'] ?? '') !== '') {
            $normalizedPhone = $this->normalizePhone($data['phone_e164']);

            if ($normalizedPhone === null) {
                return $this->recordRow($import, $rowNumber, $rawRow, ContactImportRowStatus::Rejected, "Numéro de téléphone invalide : \"{$data['phone_e164']}\".");
            }

            $data['phone_e164'] = $normalizedPhone;
        }

        [$existing, $score] = $this->findBestCandidate($import->organization_id, $data);

        if ($existing !== null && $this->calculateContactSimilarity->isDuplicate($score)) {
            return $this->handleDuplicate($import, $rowNumber, $rawRow, $data, $existing, $score);
        }

        $contact = $this->createContact($import, $data);

        return $this->recordRow($import, $rowNumber, $rawRow, ContactImportRowStatus::Accepted, null, $contact);
    }

    /**
     * @param  array<string, string|null>  $mapping  en-tête => champ Contact
     * @param  array<string, string>  $rawRow
     * @return array<string, string>
     */
    private function extractMappedFields(array $mapping, array $rawRow): array
    {
        $data = [];

        foreach ($mapping as $header => $field) {
            if ($field === null || $field === '') {
                continue;
            }

            $data[$field] = trim((string) ($rawRow[$header] ?? ''));
        }

        return $data;
    }

    private function normalizePhone(string $raw): ?string
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $parsed = $phoneUtil->parse($raw, null);
        } catch (NumberParseException) {
            return null;
        }

        if (! $phoneUtil->isValidNumber($parsed)) {
            return null;
        }

        return $phoneUtil->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: ?Contact, 1: int}
     */
    private function findBestCandidate(int $organizationId, array $data): array
    {
        if (($data['email'] ?? '') !== '') {
            $existing = Contact::query()
                ->where('organization_id', $organizationId)
                ->where('email', mb_strtolower($data['email']))
                ->first();

            if ($existing !== null) {
                return [$existing, 100];
            }
        }

        if (($data['last_name'] ?? '') === '') {
            return [null, 0];
        }

        $best = null;
        $bestScore = 0;

        Contact::query()
            ->where('organization_id', $organizationId)
            ->where('last_name', $data['last_name'])
            ->each(function (Contact $candidate) use ($data, &$best, &$bestScore): void {
                $score = $this->calculateContactSimilarity->score($data, $candidate);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $candidate;
                }
            });

        return [$best, $bestScore];
    }

    /**
     * @param  array<string, string>  $rawRow
     * @param  array<string, string>  $data
     */
    private function handleDuplicate(ContactImport $import, int $rowNumber, array $rawRow, array $data, Contact $existing, int $score): ContactImportRow
    {
        return match ($import->duplicate_strategy) {
            DuplicateStrategy::Skip => $this->recordRow(
                $import, $rowNumber, $rawRow, ContactImportRowStatus::Skipped,
                "Doublon détecté (score {$score}) avec le contact #{$existing->id}, ignoré.",
                $existing,
            ),
            DuplicateStrategy::CreateNew => $this->recordRow(
                $import, $rowNumber, $rawRow, ContactImportRowStatus::Accepted, null,
                $this->createContact($import, $data),
            ),
            DuplicateStrategy::Merge, null => $this->recordRow(
                $import, $rowNumber, $rawRow, ContactImportRowStatus::Merged,
                "Fusionné avec le contact #{$existing->id} (score {$score}).",
                $this->mergeContact($import, $existing, $data),
            ),
        };
    }

    /**
     * @param  array<string, string>  $data
     */
    private function createContact(ContactImport $import, array $data): Contact
    {
        return Contact::query()->create([
            'organization_id' => $import->organization_id,
            'household_id' => $this->householdId($import, $data),
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => isset($data['email']) && $data['email'] !== '' ? mb_strtolower($data['email']) : null,
            'phone_e164' => $data['phone_e164'] ?? null,
            'company' => $data['company'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'preferred_language' => $data['preferred_language'] ?? null,
            'email_consent' => $this->consentValue($data['email_consent'] ?? null),
            'sms_consent' => $this->consentValue($data['sms_consent'] ?? null),
            'whatsapp_consent' => $this->consentValue($data['whatsapp_consent'] ?? null),
        ]);
    }

    /**
     * Import incrémental (T-041) : une colonne absente ou vide pour cette
     * ligne ne remplace jamais une valeur déjà enregistrée sur le contact.
     *
     * @param  array<string, string>  $data
     */
    private function mergeContact(ContactImport $import, Contact $existing, array $data): Contact
    {
        $updates = [];

        foreach (['first_name', 'last_name', 'company', 'job_title', 'preferred_language', 'phone_e164'] as $field) {
            if (($data[$field] ?? '') !== '') {
                $updates[$field] = $data[$field];
            }
        }

        if (($data['email'] ?? '') !== '') {
            $updates['email'] = mb_strtolower($data['email']);
        }

        $householdId = $this->householdId($import, $data);

        if ($householdId !== null) {
            $updates['household_id'] = $householdId;
        }

        foreach (['email_consent', 'sms_consent', 'whatsapp_consent'] as $field) {
            if (($data[$field] ?? '') !== '') {
                $updates[$field] = $this->consentValue($data[$field]);
            }
        }

        $existing->update($updates);

        return $existing->fresh();
    }

    /**
     * @param  array<string, string>  $data
     */
    private function householdId(ContactImport $import, array $data): ?int
    {
        if (($data['household_name'] ?? '') === '') {
            return null;
        }

        return $this->findOrCreateHousehold->handle($import->organization, $data['household_name'])->id;
    }

    private function consentValue(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        return in_array(mb_strtolower(trim($raw)), ['oui', 'yes', 'true', '1', 'y', 'o'], true);
    }

    /**
     * @param  array<string, string>  $rawRow
     */
    private function recordRow(
        ContactImport $import,
        int $rowNumber,
        array $rawRow,
        ContactImportRowStatus $status,
        ?string $reason,
        ?Contact $contact = null,
    ): ContactImportRow {
        return ContactImportRow::query()->create([
            'organization_id' => $import->organization_id,
            'contact_import_id' => $import->id,
            'contact_id' => $contact?->id,
            'row_number' => $rowNumber,
            'raw_data' => $rawRow,
            'status' => $status,
            'reason' => $reason,
        ]);
    }
}
