<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\ImportBankStatementRequest;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatementImport;
use Modules\Finance\Models\BankTransaction;
use Modules\Finance\Services\BankImport\BankStatementImportService;
use Modules\Finance\Services\BankImport\ReconciliationService;

class BankStatementController extends Controller
{
    public function __construct(
        private readonly BankStatementImportService $importService,
        private readonly ReconciliationService $reconciliationService,
    ) {
    }

    public function index(): Response
    {
        $imports = $this->importService->list()->map(fn (BankStatementImport $import) => [
            'id'               => $import->id,
            'bankAccountLabel' => $import->bankAccount ? "{$import->bankAccount->bank_name} — {$import->bankAccount->account_name}" : null,
            'originalFilename' => $import->original_filename,
            'format'           => $import->format,
            'status'           => $import->status,
            'importedRowCount' => $import->imported_row_count,
            'errorMessage'     => $import->error_message,
            'createdAt'        => optional($import->created_at)->format('Y-m-d H:i'),
        ]);

        $transactions = $this->reconciliationService->unmatchedTransactions()->map(fn (BankTransaction $tx) => [
            'id'              => $tx->id,
            'bankAccountLabel' => $tx->bankAccount ? "{$tx->bankAccount->bank_name} — {$tx->bankAccount->account_name}" : null,
            'transactionDate' => optional($tx->transaction_date)->format('Y-m-d'),
            'description'     => $tx->description,
            'reference'       => $tx->reference,
            'amount'          => (float) $tx->amount,
            'currency'        => $tx->currency,
            'candidates'      => $this->reconciliationService->suggestCandidates($tx),
        ]);

        $accounts = BankAccount::query()
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get()
            ->map(fn (BankAccount $account) => ['value' => $account->id, 'label' => "{$account->bank_name} — {$account->account_name}"]);

        return Inertia::render('Finance::BankStatements', [
            'imports'      => $imports->values(),
            'transactions' => $transactions->values(),
            'accounts'     => $accounts,
        ]);
    }

    public function import(ImportBankStatementRequest $request): RedirectResponse
    {
        $account = BankAccount::findOrFail($request->validated('bank_account_id'));

        $this->importService->import(
            $account,
            $request->file('file'),
            $request->validated('format'),
            $request->user()->id,
        );

        return redirect()->route('finance.bank-statements.index');
    }

    public function match(Request $request, BankTransaction $bankTransaction): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([
                BankTransaction::MATCH_SUPPLIER_INVOICE,
                BankTransaction::MATCH_OUTGOING_INVOICE,
                BankTransaction::MATCH_TENANT_INVOICE,
                BankTransaction::MATCH_ORDER,
            ])],
            'id'   => ['required', 'integer'],
        ]);

        $this->reconciliationService->confirmMatch($bankTransaction, $data['type'], $data['id'], $request->user()->id);

        return redirect()->route('finance.bank-statements.index');
    }

    public function ignore(BankTransaction $bankTransaction): RedirectResponse
    {
        $this->reconciliationService->ignore($bankTransaction);

        return redirect()->route('finance.bank-statements.index');
    }
}
