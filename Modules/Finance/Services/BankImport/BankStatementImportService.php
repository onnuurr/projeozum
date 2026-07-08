<?php

namespace Modules\Finance\Services\BankImport;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatementImport;
use Modules\Finance\Models\BankTransaction;
use Throwable;

class BankStatementImportService
{
    public function __construct(
        private readonly Mt940Parser $mt940Parser,
        private readonly CsvStatementParser $csvParser,
    ) {
    }

    /**
     * @return Collection<int, BankStatementImport>
     */
    public function list(): Collection
    {
        return BankStatementImport::query()
            ->with('bankAccount:id,bank_name,account_name')
            ->orderByDesc('id')
            ->get();
    }

    public function import(BankAccount $account, UploadedFile $file, string $format, int $userId): BankStatementImport
    {
        $path = $file->store('finance/bank-statements');

        $import = BankStatementImport::create([
            'bank_account_id'   => $account->id,
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'format'            => $format,
            'status'            => BankStatementImport::STATUS_PROCESSING,
            'imported_by'       => $userId,
        ]);

        try {
            $rows = $format === BankStatementImport::FORMAT_MT940
                ? $this->mt940Parser->parse(Storage::get($path))
                : $this->csvParser->parse(Storage::path($path));

            $importedCount = DB::transaction(function () use ($rows, $account, $import) {
                $count = 0;
                foreach ($rows as $row) {
                    if (! $row['transaction_date']) {
                        continue;
                    }

                    BankTransaction::create([
                        'bank_statement_import_id' => $import->id,
                        'bank_account_id'          => $account->id,
                        'transaction_date'         => $row['transaction_date'],
                        'description'              => $row['description'] ?: null,
                        'reference'                => $row['reference'] ?: null,
                        'amount'                   => $row['amount'] ?? 0,
                        'currency'                 => $account->currency,
                        'balance_after'            => $row['balance_after'] ?? null,
                    ]);
                    $count++;
                }

                return $count;
            });

            $import->update([
                'status'              => BankStatementImport::STATUS_COMPLETED,
                'imported_row_count'  => $importedCount,
            ]);
        } catch (Throwable $e) {
            $import->update([
                'status'        => BankStatementImport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $import->fresh();
    }
}
