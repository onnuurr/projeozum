<?php

namespace Modules\Finance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Services\ProformaInvoiceService;

class ExpireProformas extends Command
{
    protected $signature = 'finance:expire-proformas';

    protected $description = 'Geçerlilik tarihi dolmuş taslak/gönderilmiş proforma faturaları expired yapar.';

    public function handle(ProformaInvoiceService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("{$count} proforma expired olarak işaretlendi.");

        return self::SUCCESS;
    }
}
