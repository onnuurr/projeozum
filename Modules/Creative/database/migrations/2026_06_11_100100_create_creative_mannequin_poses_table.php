<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_mannequin_poses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mannequin_id')
                ->constrained('creative_mannequins')
                ->cascadeOnDelete();
            // Katalog preset anahtarı + insan-okur etiket.
            $table->string('pose_key', 60);
            $table->string('label', 120)->nullable();
            // Bu poz için Gemini'ye verilen duruş yönergesi.
            $table->text('pose_prompt')->nullable();
            // Üretilen poz görseli (manken kimliği + bu duruş).
            $table->string('image_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            // Yaşam döngüsü: queued|generating|ready|failed.
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['mannequin_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_mannequin_poses');
    }
};
