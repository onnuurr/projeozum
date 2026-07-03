<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\BankAccount;
use Modules\Finance\Models\BankStatementImport;
use Modules\Finance\Models\BankTransaction;
use Modules\Finance\Models\SupplierInvoice;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private BankAccount $account;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'finance.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('finance.manage');

        $this->account = BankAccount::create([
            'bank_name'    => 'Garanti BBVA',
            'account_name' => 'Şirket TL',
            'iban'         => 'TR330006100519786457841326',
        ]);
    }

    public function test_mt940_statement_can_be_imported(): void
    {
        $mt940 = ":61:2606010601D1200,00NMSCNONREF//\r\n:86:Tedarikçi ödemesi\r\n";
        $file  = UploadedFile::fake()->createWithContent('ekstre.sta', $mt940);

        $response = $this->actingAs($this->admin)
            ->post('/finance/bank-statements/import', [
                'bank_account_id' => $this->account->id,
                'format'          => BankStatementImport::FORMAT_MT940,
                'file'            => $file,
            ]);

        $response->assertRedirect(route('finance.bank-statements.index'));

        $import = BankStatementImport::first();
        $this->assertSame(BankStatementImport::STATUS_COMPLETED, $import->status);
        $this->assertSame(1, $import->imported_row_count);
        $this->assertSame(1, BankTransaction::count());
        $this->assertSame(-1200.0, (float) BankTransaction::first()->amount);
    }

    public function test_transaction_can_be_matched_to_supplier_invoice(): void
    {
        $invoice = SupplierInvoice::create([
            'invoice_no'    => 'FTR-BANK-1',
            'supplier_name' => 'Tedarikçi X',
            'invoice_date'  => '2026-06-01',
            'subtotal'      => 1000,
            'tax_amount'    => 200,
            'total'         => 1200,
            'created_by'    => $this->admin->id,
        ]);

        $import = BankStatementImport::create([
            'bank_account_id'   => $this->account->id,
            'file_path'         => 'finance/bank-statements/fake.sta',
            'original_filename' => 'fake.sta',
            'format'            => BankStatementImport::FORMAT_MT940,
            'imported_by'       => $this->admin->id,
        ]);

        $transaction = BankTransaction::create([
            'bank_statement_import_id' => $import->id,
            'bank_account_id'          => $this->account->id,
            'transaction_date'         => '2026-06-01',
            'amount'                   => -1200,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/finance/bank-statements/transactions/{$transaction->id}/match", [
                'type' => BankTransaction::MATCH_SUPPLIER_INVOICE,
                'id'   => $invoice->id,
            ]);

        $response->assertRedirect(route('finance.bank-statements.index'));

        $transaction->refresh();
        $this->assertSame(BankTransaction::STATUS_MATCHED, $transaction->reconciliation_status);
        $this->assertSame(BankTransaction::MATCH_SUPPLIER_INVOICE, $transaction->matched_invoice_type);
        $this->assertSame($invoice->id, $transaction->matched_invoice_id);
    }
}
