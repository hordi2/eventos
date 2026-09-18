<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

use DateTimeInterface;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Ramène la première feuille d'un classeur Excel (.xlsx) au CSV que
 * traite l'import de contacts (M3.3.1 « Import CSV / Excel ») : un seul
 * format à lire ensuite, du mappage jusqu'au rapport.
 *
 * Lu en flux par OpenSpout : 10 000 lignes ne chargent pas tout le
 * classeur en mémoire.
 */
final class SpreadsheetToCsv
{
    public function handle(string $path): string
    {
        $reader = new Reader;
        $output = fopen('php://temp', 'r+');

        try {
            $reader->open($path);

            foreach ($reader->getSheetIterator() as $sheet) {
                $this->writeRows($sheet->getRowIterator(), $output);

                break;
            }
        } catch (OpenSpoutException) {
            throw new InvalidArgumentException("Ce fichier Excel n'a pas pu être lu. Enregistrez-le de nouveau au format .xlsx, ou en .csv, puis réessayez.");
        } finally {
            $reader->close();
        }

        rewind($output);
        $csv = (string) stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Les lignes vides en fin de feuille (souvent des cellules seulement
     * mises en forme) sont écartées ; les autres gardent leur place, pour que
     * le rapport désigne la même ligne que dans Excel.
     *
     * @param  iterable<Row>  $rows
     * @param  resource  $output
     */
    private function writeRows(iterable $rows, $output): void
    {
        $pendingEmpty = 0;
        $isHeader = true;

        foreach ($rows as $row) {
            $values = array_map($this->cellValue(...), $row->toArray());

            if ($isHeader) {
                // Un en-tête peut porter une explication sous son titre, dans
                // la même cellule : seul le titre sert à reconnaître la colonne.
                $values = array_map(fn (string $value): string => trim(strtok($value, "\n") ?: ''), $values);
                $isHeader = false;
            }

            if (implode('', $values) === '') {
                $pendingEmpty++;

                continue;
            }

            for (; $pendingEmpty > 0; $pendingEmpty--) {
                fputcsv($output, [''], escape: '');
            }

            fputcsv($output, $values, escape: '');
        }
    }

    private function cellValue(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i'),
            is_bool($value) => $value ? '1' : '0',
            // Excel range les nombres entiers en flottants : « 2 » et non « 2.0 ».
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }
}
