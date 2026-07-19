<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir giysi KAYNAK görseli için tek bir tespit taraması (YOLO/torchvision
 * yerine kullanılan parça dedektörü — bkz. proje lisans kararı, ROADMAP.md
 * Faz G). image_hash ile içerik bazlı dedup yapılır: aynı ürün görseli
 * birden çok pozda kullanıldığında gereksiz yeniden tarama önlenir
 * (bkz. GarmentScanService::scan). detections JSON tutulur — proje zaten
 * TryonResult.meta.garment_extras[].detected_labels[]'ı aynı şekilde
 * saklıyor, ayrı satır sorgusu gerekmediğinden çocuk tablo eklenmedi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_garment_scans', function (Blueprint $table) {
            $table->id();
            $table->string('image_hash', 40)->unique();
            $table->string('source_path')->nullable();
            // python | null — bkz. GarmentPartDetectorContract sürücü seçimi.
            $table->string('driver', 20)->default('null');
            $table->string('model_version', 60)->nullable();
            // queued|done|failed.
            $table->string('status', 20)->default('queued');
            // array<{label_key,label_display,bbox:{x,y,w,h} 0..1 normalize,confidence,source:auto|manual,crop_path?}>
            $table->json('detections')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_garment_scans');
    }
};
