<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            if (! Schema::hasColumn('brand_kits', 'hashtag_pool')) {
                $table->json('hashtag_pool')->nullable()->after('banned_words');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            if (Schema::hasColumn('brand_kits', 'hashtag_pool')) {
                $table->dropColumn('hashtag_pool');
            }
        });
    }
};
