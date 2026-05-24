<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->enum('type', ['retail', 'dealer', 'dropship']);
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('TRY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Unique sadece aktif kayıtlar için anlam taşır; soft delete kullanılır
            // ama mantıken aynı (varyant, tip) için tek aktif kayıt olmalı.
            // Kontrol uygulama katmanında yapılır; DB unique'i (variant, type) üzerinde tutuyoruz.
            // Deaktivasyon yerine kayıt silinir; soft delete sadece audit içindir.
            $table->unique(['product_variant_id', 'type'], 'price_lists_variant_type_unique');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};
