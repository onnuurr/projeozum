<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_commission_rates', function (Blueprint $table) {
            $table->id();
            $table->string('marketplace', 32);
            // category_id NULL → bu marketplace için default oran (kategori match'i yoksa).
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->decimal('commission_rate', 5, 2); // %
            $table->decimal('shipping_rate', 5, 2)->default(0); // %
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['marketplace', 'category_id', 'valid_from'], 'uq_mp_commission');
            $table->index(['marketplace', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_commission_rates');
    }
};
