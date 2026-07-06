<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('tenant_id')
                ->nullable()
                ->after('user_id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['tenant_id', 'status']);
        });

        // Geriye dönük backfill: mevcut tenant_invoices üzerinden order → tenant eşlemesi.
        // Bir order birden fazla tenant'a fatura kesilmiş olamayacağı varsayımı; PR review'da el ile teyit.
        // Cross-driver uyumluluğu için Eloquent ile (UPDATE...FROM yalnız Postgres'te).
        DB::table('tenant_invoices')
            ->whereNotNull('order_id')
            ->select('order_id', 'tenant_id')
            ->orderBy('id')
            ->get()
            ->unique('order_id')
            ->each(function ($row): void {
                DB::table('orders')
                    ->where('id', $row->order_id)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $row->tenant_id]);
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
