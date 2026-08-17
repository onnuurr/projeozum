<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagisto tarafındaki karşılığıyla (`Webkul\Customer`
 * `2026_07_27_150000_add_shipping_agreement_fields_to_customers_table`) aynı
 * alan adları/anlamı — `Modules\Bagisto\Mappers\TenantPayloadMapper` bu
 * kolonları `POST /api/saas-sync/tenants` `activated` payload'ına eşler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('shipping_agreement_type', 20)->nullable()->after('current_balance');
            $table->timestamp('shipping_terms_accepted_at')->nullable()->after('shipping_agreement_type');
            $table->string('carrier_tracking_number', 255)->nullable()->after('shipping_terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_agreement_type',
                'shipping_terms_accepted_at',
                'carrier_tracking_number',
            ]);
        });
    }
};
