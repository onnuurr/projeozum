<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('template_id')
                ->constrained('creative_templates')
                ->cascadeOnDelete();
            $table->string('image_path', 500)->nullable();
            // Üretim yaşam döngüsü.
            $table->string('status', 20)->default('queued');
            // İnsan onayı (üretimden bağımsız): pending|approved|rejected.
            $table->string('review_status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_assets');
    }
};
