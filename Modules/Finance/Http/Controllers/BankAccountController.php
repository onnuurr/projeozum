<?php

namespace Modules\Finance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Finance\Http\Requests\StoreBankAccountRequest;
use Modules\Finance\Http\Requests\UpdateBankAccountRequest;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Services\BankAccountService;

class BankAccountController extends Controller
{
    public function __construct(private readonly BankAccountService $service)
    {
    }

    public function index(): Response
    {
        $accounts = $this->service->list()->map(fn (BankAccount $account) => [
            'id'            => $account->id,
            'bankName'      => $account->bank_name,
            'accountName'   => $account->account_name,
            'iban'          => $account->iban,
            'currency'      => $account->currency,
            'accountNumber' => $account->account_number,
            'isActive'      => $account->is_active,
            'note'          => $account->note,
        ]);

        return Inertia::render('Finance::BankAccounts', [
            'accounts' => $accounts->values(),
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $this->service->create($request->validated());

        return redirect()->route('finance.bank-accounts.index');
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->service->update($bankAccount, $request->validated());

        return redirect()->route('finance.bank-accounts.index');
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->service->delete($bankAccount);

        return redirect()->route('finance.bank-accounts.index');
    }
}
