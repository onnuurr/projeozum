<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_engine_jobs', function (Blueprint $table) {
            $table->id();
            // Çağıran tarafın kendi anahtarı (ör. "product:123"). FK YOK: PatternEngine
            // hiçbir modüle bağımlı değildir (bkz. module.json allowed_dependencies: []).
            $table->string('external_reference')->nullable();
            $table->string('garment', 40);
            $table->string('size', 20);
            $table->string('profile', 60)->default('ts_default');
            $table->json('design_params')->nullable();
            // Yaşam döngüsü: queued|processing|done|failed.
            $table->string('status', 20)->default('queued');
            // Optimistic concurrency: eski bir işin çıktısının daha yeni bir talebi
            // ezmesini engeller (bkz. Creative'in generation_token deseni).
            $table->uuid('generation_token')->nullable();
            $table->string('result_dxf_path')->nullable();
            $table->json('validation_report')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_engine_jobs');
    }
};
