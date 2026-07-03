<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Finance\Models\SupplierInvoice;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SupplierInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'finance.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('finance.manage');
    }

    public function test_supplier_invoice_can_be_created_with_file(): void
    {
        $file = UploadedFile::fake()->create('fatura.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->admin)
            ->post('/finance/supplier-invoices', [
                'invoice_no'    => 'FTR-1',
                'supplier_name' => 'Kumaş A.Ş.',
                'invoice_date'  => '2026-06-01',
                'subtotal'      => 1000,
                'tax_amount'    => 200,
                'total'         => 1200,
                'status'        => 'unpaid',
                'file'          => $file,
            ]);

        $response->assertRedirect(route('finance.supplier-invoices.index'));

        $invoice = SupplierInvoice::where('invoice_no', 'FTR-1')->firstOrFail();
        $this->assertNotNull($invoice->file_path);
        Storage::disk('local')->assertExists($invoice->file_path);
    }

    public function test_supplier_invoice_index_lists_has_file_flag(): void
    {
        $invoice = SupplierInvoice::create([
            'invoice_no'    => 'FTR-2',
            'supplier_name' => 'Tedarikçi X',
            'invoice_date'  => '2026-06-01',
            'subtotal'      => 500,
            'tax_amount'    => 100,
            'total'         => 600,
            'file_path'     => 'finance/supplier-invoices/existing.pdf',
            'created_by'    => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/finance/supplier-invoices');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance::SupplierInvoices', false)
                ->where('invoices.0.hasFile', true)
                ->where('invoices.0.id', $invoice->id)
            );
    }

    public function test_downloading_file_requires_it_to_exist(): void
    {
        $invoice = SupplierInvoice::create([
            'invoice_no'    => 'FTR-3',
            'supplier_name' => 'Tedarikçi Y',
            'invoice_date'  => '2026-06-01',
            'subtotal'      => 100,
            'tax_amount'    => 0,
            'total'         => 100,
            'created_by'    => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->get("/finance/supplier-invoices/{$invoice->id}/file")
            ->assertNotFound();
    }

    public function test_supplier_invoice_can_be_marked_as_paid(): void
    {
        $invoice = SupplierInvoice::create([
            'invoice_no'    => 'FTR-4',
            'supplier_name' => 'Tedarikçi Z',
            'invoice_date'  => '2026-06-01',
            'subtotal'      => 100,
            'tax_amount'    => 0,
            'total'         => 100,
            'created_by'    => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/finance/supplier-invoices/{$invoice->id}/mark-paid")
            ->assertRedirect(route('finance.supplier-invoices.index'));

        $this->assertSame(SupplierInvoice::STATUS_PAID, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_non_permitted_user_cannot_access_supplier_invoices(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/finance/supplier-invoices')
            ->assertForbidden();
    }
}
