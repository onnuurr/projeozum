<?php

namespace Modules\Finance\Services\BankImport;

use DateTime;

/**
 * Minimal MT940 (SWIFT) ekstre parser'ı. Sadece `:61:` (işlem satırı) ve
 * `:86:` (açıklama) tag'lerini işler; harici bir paket yerine dahili, dar
 * kapsamlı bir parser tercih edildi (format nispeten sabit ve dar kapsamlı).
 */
class Mt940Parser
{
    /**
     * @return array<int, array{transaction_date: ?string, description: string, reference: ?string, amount: float, balance_after: null}>
     */
    public function parse(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $rows = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, ':61:')) {
                if ($current !== null) {
                    $rows[] = $current;
                }
                $current = $this->parseStatementLine($line);
            } elseif ($current !== null && str_starts_with($line, ':86:')) {
                $current['description'] = trim(substr($line, 4));
            } elseif ($current !== null && $line !== '' && ! str_starts_with($line, ':')) {
                // Çok satırlı :86: açıklama devamı.
                $current['description'] = trim($current['description'] . ' ' . $line);
            }
        }

        if ($current !== null) {
            $rows[] = $current;
        }

        return $rows;
    }

    /**
     * @return array{transaction_date: ?string, description: string, reference: ?string, amount: float, balance_after: null}
     */
    private function parseStatementLine(string $line): array
    {
        // :61:YYMMDD[MMDD]C|D[R][amount kuruş virgüllü]...
        if (! preg_match('/^:61:(\d{6})(?:\d{4})?(C|D)R?([\d,]+)/', $line, $matches)) {
            return ['transaction_date' => null, 'description' => '', 'reference' => null, 'amount' => 0.0, 'balance_after' => null];
        }

        [, $valueDate, $mark, $amountRaw] = $matches;

        $date   = DateTime::createFromFormat('ymd', $valueDate);
        $amount = (float) str_replace(',', '.', $amountRaw);

        if ($mark === 'D') {
            $amount = -$amount;
        }

        return [
            'transaction_date' => $date ? $date->format('Y-m-d') : null,
            'description'      => '',
            'reference'        => null,
            'amount'           => $amount,
            'balance_after'    => null,
        ];
    }
}
