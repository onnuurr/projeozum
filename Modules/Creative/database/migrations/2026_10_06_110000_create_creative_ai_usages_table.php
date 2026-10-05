<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI çağrı maliyet kaydı: Creative'in ortak AI istemcilerinden (GeminiClient, FalClient) geçen
 * her BAŞARILI çağrı bir satır. est_cost_usd resmi fiyat listesine göre TAHMİNDİR
 * (config creative.ai.pricing); fatura kesin kaynaktır. subject_* çağrının ait olduğu iş
 * (mokap kombini, baz görsel, giydirme sonucu...). Prunable (bkz. AiUsage).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creative_ai_usages')) {
            return;
        }

        Schema::create('creative_ai_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('model', 120);
            $table->string('operation', 20);
            $table->string('image_size', 10)->nullable();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedSmallInteger('images_out')->default(0);
            $table->decimal('est_cost_usd', 10, 5)->default(0);
            $table->nullableMorphs('subject');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_ai_usages');
    }
};
