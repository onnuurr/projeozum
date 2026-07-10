<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * warehouses'a is_default bayrağı ekler.
 *
 * Sipariş anındaki stok tahsisi (D2) önce varsayılan depodan başlar. Tek-varsayılan
 * kuralı (aynı anda yalnızca bir is_default=true) service/controller katmanında
 * uygulanır; DB seviyesinde partial unique index yerine uygulama kuralı tercih edildi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (! Schema::hasColumn('warehouses', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (Schema::hasColumn('warehouses', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });
    }
};
