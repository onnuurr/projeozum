<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_mannequins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // AI üretim tarifi alanları (kimlik prompt'unu oluşturur).
            $table->string('gender', 20)->nullable();
            $table->string('age_range', 30)->nullable();
            $table->string('skin_tone', 30)->nullable();
            $table->string('body_type', 30)->nullable();
            $table->string('hair', 60)->nullable();
            // Serbest ek tarif (operatör notu) ve son üretilen tam prompt (izlenebilirlik).
            $table->text('extras')->nullable();
            $table->text('prompt')->nullable();
            // Üretilen kimlik referans görseli (tüm pozlarda referans olarak verilir).
            $table->string('reference_image_path', 500)->nullable();
            // Yaşam döngüsü: draft|generating|ready|failed.
            $table->string('status', 20)->default('draft');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_mannequins');
    }
};
