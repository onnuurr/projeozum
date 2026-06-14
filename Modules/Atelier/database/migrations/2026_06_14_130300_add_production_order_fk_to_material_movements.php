<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_movements', function (Blueprint $table) {
            $table->foreign('production_order_id')
                ->references('id')->on('production_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_movements', function (Blueprint $table) {
            $table->dropForeign(['production_order_id']);
        });
    }
};
