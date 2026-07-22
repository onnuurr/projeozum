<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ret seçim maddelerini ekrana göre ayırmak için "context" alanı.
 *
 * Şimdiye dek tek liste hem Creative galerisi hem Manken hem de Model Giydirme
 * (tryon) reddet diyaloglarında aynen gösteriliyordu. NULL context = "tüm
 * ekranlarda göster" (mevcut maddelerin geriye dönük davranışı budur); dolu
 * değer = yalnız o ekranda göster (bkz. RejectionReason::CONTEXTS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_rejection_reasons', function (Blueprint $table) {
            $table->string('context', 20)->nullable()->after('category');
            $table->index(['context', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('creative_rejection_reasons', function (Blueprint $table) {
            $table->dropIndex(['context', 'is_active', 'sort_order']);
            $table->dropColumn('context');
        });
    }
};
