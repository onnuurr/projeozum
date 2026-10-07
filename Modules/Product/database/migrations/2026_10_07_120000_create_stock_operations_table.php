<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toplu stok işlemleri (bkz. StockService::bulk()): tek seferde birden çok varyantın stoğunu
 * değiştiren işlemin başlık kaydı. Satır ayrıntısı stock_movements'ta (reference = bu işlem).
 * Düşük hacimli iş kaydı — Prunable değil, bilerek korunur (stok denetim izi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('mode', 16);                       // in | out | count
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('line_count')->default(0);   // hareket yazılan varyant sayısı
            $table->unsignedInteger('skipped_count')->default(0); // sayımda değişmeyen varyantlar
            $table->integer('total_in')->default(0);
            $table->integer('total_out')->default(0);
            $table->timestamps();

            $table->index(['warehouse_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_operations');
    }
};
