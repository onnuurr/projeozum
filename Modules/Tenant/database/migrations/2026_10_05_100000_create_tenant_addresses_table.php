<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * İş ortağının fatura / sevk / iade adresleri (her türden bir tane). Eski tek adres
 * (tenants.address/city/district/country/postal_code) üç türe de kopyalanır; kolonlar
 * bir sonraki migration'da kaldırılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenant_addresses')) {
            Schema::create('tenant_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('type', 16);
                $table->string('contact_name', 191)->nullable();
                $table->string('phone', 32)->nullable();
                $table->text('address');
                $table->string('district', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('postal_code', 16)->nullable();
                $table->string('country', 100)->default('TR');
                $table->timestamps();

                $table->unique(['tenant_id', 'type']);
            });
        }

        if (! Schema::hasColumn('tenants', 'address')) {
            return;
        }

        DB::table('tenants')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->orderBy('id')
            ->each(function ($tenant) {
                foreach (['billing', 'shipping', 'return'] as $type) {
                    DB::table('tenant_addresses')->insertOrIgnore([
                        'tenant_id' => $tenant->id,
                        'type' => $type,
                        'contact_name' => $tenant->contact_person,
                        'phone' => $tenant->contact_phone ?: $tenant->phone,
                        'address' => $tenant->address,
                        'district' => $tenant->district,
                        'city' => $tenant->city,
                        'postal_code' => $tenant->postal_code,
                        'country' => $tenant->country ?: 'TR',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_addresses');
    }
};
