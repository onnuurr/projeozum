<?php

namespace Tests\Feature\Tenant;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Tenant\Models\TenantApplication;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.manage', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'tenant.view', 'guard_name' => 'web']);
        $role->givePermissionTo(['tenant.manage', 'tenant.view']);
        $this->staff = User::factory()->create();
        $this->staff->assignRole($role);

        Storage::fake('local');
        Queue::fake();
    }

    private function makeApplication(array $overrides = []): TenantApplication
    {
        return TenantApplication::create(array_merge([
            'name'           => 'ABC Tekstil',
            'tax_number'     => '1234567899',
            'contact_person' => 'Ahmet Yılmaz',
            'contact_email'  => 'ahmet@abctekstil.com',
            'status'         => TenantApplication::STATUS_PENDING,
        ], $overrides));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'ABC Tekstil',
            'legal_name'     => 'ABC Tekstil San. Tic. Ltd. Şti.',
            'tax_number'     => '1234567899', // geçerli GİB test VKN'i
            'contact_person' => 'Ahmet Yılmaz',
            'contact_email'  => 'ahmet@abctekstil.com',
            'vergi_levhasi'  => UploadedFile::fake()->create('vergi-levhasi.pdf', 200, 'application/pdf'),
        ], $overrides);
    }

    public function test_public_can_submit_application(): void
    {
        $response = $this->post('/tenant-basvuru', $this->payload());

        $response->assertRedirect(route('tenant-applications.create'));
        $this->assertDatabaseHas('tenant_applications', [
            'name'       => 'ABC Tekstil',
            'tax_number' => '1234567899',
            'status'     => TenantApplication::STATUS_PENDING,
        ]);

        $application = TenantApplication::firstOrFail();
        $this->assertCount(1, $application->documents);
        Storage::disk('local')->assertExists($application->documents->first()->path);
    }

    public function test_invalid_tax_number_is_rejected(): void
    {
        $response = $this->post('/tenant-basvuru', $this->payload(['tax_number' => '1234567890']));

        $response->assertSessionHasErrors(['tax_number']);
        $this->assertDatabaseMissing('tenant_applications', ['name' => 'ABC Tekstil']);
    }

    public function test_missing_required_document_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['vergi_levhasi']);

        $response = $this->post('/tenant-basvuru', $payload);

        $response->assertSessionHasErrors(['vergi_levhasi']);
    }

    public function test_honeypot_field_blocks_bots(): void
    {
        $response = $this->post('/tenant-basvuru', $this->payload(['website' => 'https://spam.example']));

        $response->assertSessionHasErrors(['website']);
    }

    public function test_guest_cannot_view_application_queue(): void
    {
        $this->getJson('/tenants/applications')->assertUnauthorized();
    }

    public function test_non_permitted_user_cannot_approve(): void
    {
        $application = $this->makeApplication();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post("/tenants/applications/{$application->id}/approve")
            ->assertForbidden();
    }

    public function test_staff_can_approve_and_reject(): void
    {
        $approved = $this->makeApplication(['tax_number' => '1234567899']);
        $rejected = $this->makeApplication(['tax_number' => '1111111115', 'contact_email' => 'reject@example.com']);

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$approved->id}/approve")
            ->assertRedirect();
        $this->assertSame(TenantApplication::STATUS_APPROVED, $approved->fresh()->status);
        $this->assertSame($this->staff->id, $approved->fresh()->reviewed_by);

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$rejected->id}/reject", ['rejection_reason' => 'Belgeler unvan ile uyuşmuyor'])
            ->assertRedirect();
        $this->assertSame(TenantApplication::STATUS_REJECTED, $rejected->fresh()->status);
        $this->assertSame('Belgeler unvan ile uyuşmuyor', $rejected->fresh()->rejection_reason);
    }

    public function test_reject_requires_reason(): void
    {
        $application = $this->makeApplication();

        $this->actingAs($this->staff)
            ->post("/tenants/applications/{$application->id}/reject", [])
            ->assertSessionHasErrors(['rejection_reason']);
    }

    public function test_document_download_rejects_mismatched_application(): void
    {
        $this->post('/tenant-basvuru', $this->payload());
        $application = TenantApplication::firstOrFail();
        $document = $application->documents->first();

        $otherApplication = $this->makeApplication(['tax_number' => '1111111115', 'contact_email' => 'other@example.com']);

        $this->actingAs($this->staff)
            ->get("/tenants/applications/{$otherApplication->id}/documents/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_approved_application_can_be_converted_to_tenant_and_links_back(): void
    {
        Role::firstOrCreate(['name' => 'tenant', 'guard_name' => 'web']);
        $application = $this->makeApplication(['status' => TenantApplication::STATUS_APPROVED]);

        $response = $this->actingAs($this->staff)->post('/tenants', [
            'code'                        => 'ABC01',
            'name'                        => $application->name,
            'tax_number'                  => $application->tax_number,
            'owner_name'                  => 'Ahmet Yılmaz',
            'owner_email'                 => 'ahmet@abctekstil.com',
            'owner_password'              => 'password123',
            'owner_password_confirmation' => 'password123',
            'tenant_application_id'       => $application->id,
        ]);

        $response->assertRedirect(route('tenants.index'));

        $tenant = \Modules\Tenant\Models\Tenant::where('code', 'ABC01')->firstOrFail();
        $this->assertSame($tenant->id, $application->fresh()->converted_tenant_id);
    }
}
