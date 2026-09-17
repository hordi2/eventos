<?php

declare(strict_types=1);

namespace App\Domain\Form\Support;

/**
 * Bloc « Fichier joint » (CDC M2.1 : types autorisés, taille max,
 * antivirus). Réglages (config) : file_types parmi TYPES et max_size_mb
 * jusqu'à MAX_SIZE_MB. L'invité envoie le fichier sous {INPUT_KEY}[clé] ;
 * la réponse garde ensuite sa référence (RegistrationFile::token), jamais
 * le fichier lui-même.
 */
final class FileUploadAnswer
{
    public const INPUT_KEY = '_fichiers';

    public const MAX_SIZE_MB = 10;

    // Fichier jamais rattaché à une inscription (brouillon abandonné) : supprimé passé ce délai.
    public const UNCLAIMED_RETENTION_DAYS = 30;

    /**
     * @var array<string, array{label: string, extensions: list<string>}>
     */
    public const TYPES = [
        'images' => ['label' => 'Images (JPG, PNG, WEBP, HEIC)', 'extensions' => ['jpg', 'jpeg', 'png', 'webp', 'heic']],
        'pdf' => ['label' => 'PDF', 'extensions' => ['pdf']],
        'documents' => ['label' => 'Documents (Word, Excel, OpenDocument)', 'extensions' => ['doc', 'docx', 'xls', 'xlsx', 'odt', 'ods']],
    ];

    /**
     * @var list<string>
     */
    public const DEFAULT_TYPES = ['images', 'pdf'];

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function types(array $config): array
    {
        $chosen = is_array($config['file_types'] ?? null) ? $config['file_types'] : [];
        $types = array_values(array_filter(array_keys(self::TYPES), fn (string $type): bool => in_array($type, $chosen, true)));

        return $types === [] ? self::DEFAULT_TYPES : $types;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function extensions(array $config): array
    {
        $extensions = [];

        foreach (self::types($config) as $type) {
            $extensions = [...$extensions, ...self::TYPES[$type]['extensions']];
        }

        return $extensions;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function maxSizeMb(array $config): int
    {
        $size = is_numeric($config['max_size_mb'] ?? null) ? (int) $config['max_size_mb'] : self::MAX_SIZE_MB;

        return max(1, min(self::MAX_SIZE_MB, $size));
    }

    /**
     * Type réel (lu dans le contenu), extension et taille : les contrôles du
     * §7 du CLAUDE.md, avant tout passage en quarantaine.
     *
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public static function uploadRules(array $config): array
    {
        $extensions = implode(',', self::extensions($config));

        return ['file', 'max:'.(self::maxSizeMb($config) * 1024), "mimes:{$extensions}", "extensions:{$extensions}"];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    public static function uploadMessages(array $config): array
    {
        $refused = "Ce type de fichier n'est pas accepté ici. Formats possibles : ".self::typesLabel($config).'.';

        return [
            'uploaded' => "L'envoi du fichier a échoué : il dépasse sans doute la taille autorisée.",
            'file' => "L'envoi du fichier a échoué. Réessayez.",
            'max' => 'Ce fichier dépasse '.self::maxSizeMb($config).' Mo.',
            'mimes' => $refused,
            'extensions' => $refused,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function typesLabel(array $config): string
    {
        return implode(', ', array_map(fn (string $type): string => self::TYPES[$type]['label'], self::types($config)));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function acceptAttribute(array $config): string
    {
        return implode(',', array_map(fn (string $extension): string => ".{$extension}", self::extensions($config)));
    }

    /**
     * Référence du fichier d'une réponse enregistrée.
     */
    public static function storedToken(mixed $value): ?string
    {
        return is_array($value) && is_string($value['token'] ?? null) ? $value['token'] : null;
    }

    /**
     * Taille lisible, en calcul entier : « 850 Ko », « 2,4 Mo ».
     */
    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return max(1, intdiv($bytes + 1023, 1024)).' Ko';
        }

        $tenths = intdiv($bytes * 10, 1024 * 1024);

        return intdiv($tenths, 10).','.($tenths % 10).' Mo';
    }
}
