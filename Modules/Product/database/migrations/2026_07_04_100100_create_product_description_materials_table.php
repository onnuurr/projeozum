<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_description_materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $t->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $t->string('role', 32);
            $t->unsignedInteger('sort_order')->default(0);
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['product_id', 'material_id', 'role'], 'pdm_product_material_role_unique');
            $t->index(['product_id', 'sort_order'], 'pdm_product_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_description_materials');
    }
};
