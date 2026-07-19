<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Giysi parça/tip tespitlerinin kanonik adlandırma sözlüğü (yaka, cep, etek vb.).
 * Hem manuel kutu-etiketleme aracı hem otomatik tespit sürücüsü bir etiket
 * anahtarı ürettiğinde bu tabloda "bulunamazsa oluştur" (firstOrCreate) ile
 * eşleşir — {@see \Modules\Creative\Services\GarmentScanService}. Bu, aynı
 * parçanın farklı yazımlarla (Yaka/yaka) yinelenmeden tek bir kanonik kayda
 * toplanmasını sağlar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_garment_labels', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('display', 80);
            // part (yaka/cep/kol ucu) | garment_type (etek/pantolon) | angle (arkadan/yandan).
            // Postgres'te native enum yerine string + model katmanında kontrol (proje deseni).
            $table->string('group', 20)->default('part');
            // seed: classify_garment_detail.py kategorileriyle başlangıçta eklenenler.
            // auto_created: kullanıcı/model zamanla yeni bir isim ürettiğinde kendiliğinden açılan kayıt.
            $table->string('source', 20)->default('auto_created');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_garment_labels');
    }
};
