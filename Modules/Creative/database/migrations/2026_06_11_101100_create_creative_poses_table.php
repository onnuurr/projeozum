<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_poses', function (Blueprint $table) {
            $table->id();
            // Katalog/operatör tanımlı poz; mankenden BAĞIMSIZ.
            $table->string('pose_key', 60)->unique();
            $table->string('label', 120);
            // Gemini'ye verilen duruş yönergesi.
            $table->text('prompt');
            // Nötr figür üzerinde üretilen önizleme görseli (poz kütüphanesi vitrini).
            $table->string('preview_image_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            // Yaşam döngüsü: draft|generating|ready|failed.
            $table->string('status', 20)->default('draft');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_poses');
    }
};
