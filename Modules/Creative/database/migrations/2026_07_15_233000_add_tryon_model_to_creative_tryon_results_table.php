<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bir giydirme sonucunun hangi AI sürücüsü/modeliyle üretildiğini kalıcı olarak
 * saklar (raporlama + geriye dönük teşhis için). tryon_driver sürücü anahtarı
 * (fal|gemini|mock), tryon_model o sürücünün o anki config'teki tam model adıdır
 * (ör. fal-ai/fashn/tryon/v1.6). Üretim başarısız olursa ikisi de null kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'tryon_driver')) {
                $table->string('tryon_driver', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'tryon_model')) {
                $table->string('tryon_model', 150)->nullable()->after('tryon_driver');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'tryon_model')) {
                $table->dropColumn('tryon_model');
            }
            if (Schema::hasColumn('creative_tryon_results', 'tryon_driver')) {
                $table->dropColumn('tryon_driver');
            }
        });
    }
};
