<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Finance\Models\OutgoingInvoice;
use Modules\Finance\Models\ProformaInvoice;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProformaConversionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
        Permission::firstOrCreate(['name' => 'finance.manage', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->givePermissionTo('finance.manage');
    }

    public function test_standalone_proforma_can_be_created_with_items(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/finance/proformas', [
                'proforma_no' => 'PRF-1',
                'buyer_name'  => 'ABC Ltd',
                'issue_date'  => '2026-07-01',
                'valid_until' => '2026-07-15',
                'subtotal'    => 500,
                'tax_amount'  => 100,
                'total'       => 600,
                'items'       => [
                    ['description' => 'Ürün A', 'qty' => 2, 'unit_price' => 250, 'total_price' => 500],
                ],
            ]);

        $response->assertRedirect(route('finance.proformas.index'));

        $proforma = ProformaInvoice::where('proforma_no', 'PRF-1')->firstOrFail();
        $this->assertSame(1, $proforma->items()->count());
    }

    public function test_standalone_proforma_requires_items(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/finance/proformas', [
                'proforma_no' => 'PRF-2',
                'buyer_name'  => 'ABC Ltd',
                'issue_date'  => '2026-07-01',
                'valid_until' => '2026-07-15',
                'subtotal'    => 500,
                'tax_amount'  => 100,
                'total'       => 600,
            ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_proforma_converts_to_outgoing_invoice(): void
    {
        $proforma = ProformaInvoice::create([
            'proforma_no' => 'PRF-3',
            'buyer_name'  => 'XYZ Ltd',
            'issue_date'  => '2026-07-01',
            'valid_until' => '2026-07-15',
            'subtotal'    => 500,
            'tax_amount'  => 100,
            'total'       => 600,
            'created_by'  => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/finance/proformas/{$proforma->id}/convert");

        $response->assertRedirect(route('finance.proformas.index'));

        $proforma->refresh();
        $this->assertSame(ProformaInvoice::STATUS_CONVERTED, $proforma->status);
        $this->assertNotNull($proforma->converted_invoice_id);

        $invoice = OutgoingInvoice::find($proforma->converted_invoice_id);
        $this->assertNotNull($invoice);
        $this->assertSame('XYZ Ltd', $invoice->buyer_name);
        $this->assertSame(600.0, (float) $invoice->total);
        $this->assertSame($proforma->id, $invoice->converted_from_proforma_id);
    }

    public function test_sending_outgoing_invoice_uses_null_provider(): void
    {
        $invoice = OutgoingInvoice::create([
            'invoice_no'  => 'INV-1',
            'buyer_name'  => 'ABC Ltd',
            'issue_date'  => '2026-07-01',
            'subtotal'    => 500,
            'tax_amount'  => 100,
            'total'       => 600,
            'created_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->post("/finance/outgoing-invoices/{$invoice->id}/send")
            ->assertRedirect(route('finance.outgoing-invoices.index'));

        $invoice->refresh();
        $this->assertSame(OutgoingInvoice::EFATURA_NOT_SENT, $invoice->efatura_status);
        $this->assertNull($invoice->efatura_uuid);
        $this->assertNull($invoice->sent_at);
    }
}
