<?php

namespace Modules\Finance\Services;

use Illuminate\Support\Collection;
use Modules\Finance\Models\BankAccount;

class BankAccountService
{
    /**
     * @return Collection<int, BankAccount>
     */
    public function list(): Collection
    {
        return BankAccount::query()->orderBy('bank_name')->get();
    }

    public function create(array $data): BankAccount
    {
        return BankAccount::create($data);
    }

    public function update(BankAccount $account, array $data): BankAccount
    {
        $account->update($data);

        return $account->fresh();
    }

    public function delete(BankAccount $account): void
    {
        $account->delete();
    }

    public function accountCount(): int
    {
        return BankAccount::query()->count();
    }
}
