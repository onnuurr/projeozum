<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Finance yetki ayrımı (2026-09-24): finance.view tüm GET'ler, finance.manage yazma,
 * finance.einvoice.send e-Fatura gönderme (ProformaConversionTest'te test edilir).
 */
class FinancePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_view_only_user_can_read_but_not_write(): void
    {
        $viewer = $this->user(['finance.view']);

        foreach (['/finance', '/finance/sales', '/finance/supplier-invoices', '/finance/proformas',
            '/finance/outgoing-invoices', '/finance/bank-accounts', '/finance/bank-statements'] as $url) {
            $this->actingAs($viewer)->get($url)->assertOk();
        }

        $this->actingAs($viewer)->post('/finance/bank-accounts', ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post('/finance/proformas', [])->assertForbidden();
    }

    public function test_manage_without_view_cannot_open_pages(): void
    {
        $this->actingAs($this->user(['finance.manage']))->get('/finance')->assertForbidden();
    }
}
