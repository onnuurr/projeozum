<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('marketplace', 32);
            $table->string('expense_type', 16); // commission/shipping/return/ads/other
            $table->foreignId('marketplace_sale_id')->nullable()->constrained('marketplace_sales')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('description')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'marketplace', 'expense_type']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_expenses');
    }
};
