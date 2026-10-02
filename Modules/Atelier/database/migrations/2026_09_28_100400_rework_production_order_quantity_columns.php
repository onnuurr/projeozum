<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kısmi stok girişi: "üretilen" adet artık aşamalardan türer, iş emri/varyant düzeyinde
 * tutulan tek miktar stoğa ALINAN adettir (produced_qty → received_qty). Varyant bazında
 * fire kaldırıldı — fire aşama partilerinde (production_step_batches) tutulur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->renameColumn('produced_qty', 'received_qty');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('unit_cost');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('production_order_items', function (Blueprint $table) {
            $table->renameColumn('produced_qty', 'received_qty');
        });

        Schema::table('production_order_items', function (Blueprint $table) {
            $table->dropColumn('scrap_qty');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_items', function (Blueprint $table) {
            $table->unsignedInteger('scrap_qty')->default(0)->after('received_qty');
        });

        Schema::table('production_order_items', function (Blueprint $table) {
            $table->renameColumn('received_qty', 'produced_qty');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('closed_at');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->renameColumn('received_qty', 'produced_qty');
        });
    }
};
