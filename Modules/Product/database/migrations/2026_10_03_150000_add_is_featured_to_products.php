<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Öne çıkan" ürün: mağazanın (Bagisto) vitrin karuselindeki ürünler. Önceden Bagisto admininde
 * elle işaretleniyordu; artık SaaS ürün formundan yönetilir ve `featured` olarak gönderilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_new');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
