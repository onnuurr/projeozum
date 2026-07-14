<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_marketplace_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('product_categories')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_id')
                ->constrained('marketplaces')
                ->cascadeOnDelete();
            $table->string('category_path');
            $table->string('external_id', 64)->nullable();
            $table->unsignedInteger('synced_products')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['category_id', 'marketplace_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_marketplace_mappings');
    }
};
