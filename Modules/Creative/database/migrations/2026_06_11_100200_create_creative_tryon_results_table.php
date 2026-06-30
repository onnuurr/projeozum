<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_tryon_results', function (Blueprint $table) {
            $table->id();
            // Sonucun bağlandığı ürün; nihai görsel product_images'a yazılır.
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            // Kaynak takibi: manken silinse bile sonuç izi korunsun (null'a düşer).
            $table->foreignId('mannequin_id')
                ->nullable()
                ->constrained('creative_mannequins')
                ->nullOnDelete();
            $table->foreignId('pose_id')
                ->nullable()
                ->constrained('creative_mannequin_poses')
                ->nullOnDelete();
            // Üretilen ürün görseline işaret (Product modülü product_images tablosu).
            $table->foreignId('product_image_id')
                ->nullable()
                ->constrained('product_images')
                ->nullOnDelete();
            // Yaşam döngüsü: queued|generating|done|failed.
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            // Aynı ürün+poz kombinasyonu tek sonuç (idempotensi; yeniden üretim aynı satırı günceller).
            $table->unique(['product_id', 'pose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_tryon_results');
    }
};
