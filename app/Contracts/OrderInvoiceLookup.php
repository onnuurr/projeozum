<?php

namespace App\Contracts;

/**
 * Bir siparişin bizim tarafımızda faturalandırılıp faturalandırılmadığı. Bagisto modülü
 * Finance'e bağımlı olamadığı için (Modules/Bagisto/module.json allowed_dependencies) bu
 * kernel sözleşmesini kullanır; tek sürücü Modules\Finance\Services\OrderInvoiceLookup
 * (FinanceServiceProvider::register()).
 *
 * Kullanım: faturalandırılmış bir siparişin iadesinde, bayinin iade faturası kontrol
 * edilmeden para iadesi yapılamaz (bkz. Modules\Bagisto\Services\BagistoReturnService).
 * Binding yoksa çağıran taraf "faturalı" varsayar (güvenli taraf: fatura şartı aranır).
 */
interface OrderInvoiceLookup
{
    public function hasIssuedInvoice(int $orderId): bool;
}
