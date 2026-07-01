<?php

namespace Modules\Finance\Services\BankImport;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * CSV/XLSX banka ekstresi parser'ı (maatwebsite/excel). Banka export formatı
 * firmadan firmaya değiştiği için başlık satırı Türkçe/İngilizce yaygın
 * varyantlarla eşleştirilir; tanınmayan kolonlar yok sayılır.
 */
class CsvStatementParser
{
    private const HEADER_MAP = [
        'date'        => ['tarih', 'işlem tarihi', 'islem tarihi', 'date', 'transaction date', 'value date'],
        'description' => ['açıklama', 'aciklama', 'description', 'işlem açıklaması', 'islem aciklamasi'],
        'reference'   => ['referans', 'reference', 'dekont no', 'dekont numarası', 'fiş no', 'fis no'],
        'amount'      => ['tutar', 'amount', 'işlem tutarı', 'islem tutari'],
        'balance'     => ['bakiye', 'balance', 'kalan bakiye'],
    ];

    /**
     * @return array<int, array{transaction_date: ?string, description: string, reference: ?string, amount: float, balance_after: ?float}>
     */
    public function parse(string $filePath): array
    {
        $sheets = Excel::toArray([], $filePath);
        $rows   = $sheets[0] ?? [];

        if (count($rows) < 2) {
            return [];
        }

        $header  = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);
        $columns = $this->mapColumns($header);

        $parsed = [];
        foreach (array_slice($rows, 1) as $row) {
            if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                continue;
            }

            $parsed[] = [
                'transaction_date' => $columns['date'] !== null ? $this->parseDate($row[$columns['date']] ?? null) : null,
                'description'      => $columns['description'] !== null ? (string) ($row[$columns['description']] ?? '') : '',
                'reference'        => $columns['reference'] !== null ? ($row[$columns['reference']] ?? null) : null,
                'amount'           => $columns['amount'] !== null ? ($this->parseAmount($row[$columns['amount']] ?? 0) ?? 0.0) : 0.0,
                'balance_after'    => $columns['balance'] !== null ? $this->parseAmount($row[$columns['balance']] ?? null) : null,
            ];
        }

        return $parsed;
    }

    /**
     * @param array<int, string> $header
     * @return array<string, int|null>
     */
    private function mapColumns(array $header): array
    {
        $columns = ['date' => null, 'description' => null, 'reference' => null, 'amount' => null, 'balance' => null];

        foreach (self::HEADER_MAP as $key => $aliases) {
            foreach ($header as $idx => $col) {
                if (in_array($col, $aliases, true)) {
                    $columns[$key] = $idx;
                    break;
                }
            }
        }

        return $columns;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        // Türkçe binlik/ondalık ayracı: "1.234,56" -> 1234.56
        $normalized = str_replace('.', '', (string) $value);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
