<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `carrier_tracking_number` yanlış varsayıma dayanıyordu: takip/barkod
 * numarası siparişe özeldir, tenant seviyesinde sabit bir değer olamaz.
 * Gerçek ihtiyaç, bayinin kullandığı kargo FİRMASININ seçimidir — merkezi
 * `carriers` tablosuna (Modules\Product\Models\Carrier) referans.
 * `Modules\Bagisto\Mappers\TenantPayloadMapper` bunu Bagisto'ya `carrier_name`
 * olarak gönderir (bkz. Bagisto `2026_08_10_150000_rename_carrier_tracking_
 * number_to_carrier_name_on_customers_table`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('carrier_tracking_number');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('carrier_id')->nullable()->after('shipping_agreement_type')
                ->constrained('carriers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('carrier_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('carrier_tracking_number', 255)->nullable()->after('shipping_agreement_type');
        });
    }
};
