<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tek adres kolonları tenant_addresses'e taşındı (bkz. 2026_10_05_100000). down() kolonları
 * geri kurar ve fatura adresinden doldurur.
 */
return new class extends Migration
{
    private const COLUMNS = ['address', 'city', 'district', 'country', 'postal_code'];

    public function up(): void
    {
        $existing = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('tenants', $c)));

        if ($existing) {
            Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn($existing));
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('country', 100)->nullable()->default('TR');
            $table->string('postal_code', 16)->nullable();
        });

        DB::table('tenant_addresses')->where('type', 'billing')->orderBy('id')->each(function ($a) {
            DB::table('tenants')->where('id', $a->tenant_id)->update([
                'address' => $a->address,
                'city' => $a->city,
                'district' => $a->district,
                'country' => $a->country,
                'postal_code' => $a->postal_code,
            ]);
        });
    }
};
