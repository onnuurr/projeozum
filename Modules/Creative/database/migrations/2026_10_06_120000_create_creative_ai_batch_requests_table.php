<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gemini Batch API (%50 indirim) kuyruğu: batch modunda her görsel çağrısı bir satır olur.
 * key, çağrının mantıksal kimliğidir (ör. mockup_base:12:1) — iş kaldığı yerden devam ederken aynı
 * anahtarla sonucu buradan alır, çağrı ikinci kez gönderilmez. İstek/yanıt gövdeleri (base64 görsel)
 * DB'de değil iş diskinde dosya olarak durur (payload_path, response_path). resume: sonuç gelince
 * yeniden kuyruğa alınacak iş (sınıf + argümanlar). Prunable (bkz. AiBatchRequest).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creative_ai_batch_requests')) {
            return;
        }

        Schema::create('creative_ai_batch_requests', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->string('provider', 20)->default('gemini');
            $table->string('model', 120);
            $table->string('operation', 20);
            $table->string('status', 20)->default('queued');
            $table->string('batch_name', 191)->nullable()->index();
            $table->string('payload_path', 255)->nullable();
            $table->string('response_path', 255)->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('subject');
            $table->json('resume')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_ai_batch_requests');
    }
};
