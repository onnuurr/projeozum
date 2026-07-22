<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            if (! Schema::hasColumn('brand_kits', 'design_brief')) {
                $table->text('design_brief')->nullable()->after('spacing');
            }
            if (! Schema::hasColumn('brand_kits', 'tone')) {
                $table->string('tone', 191)->nullable()->after('design_brief');
            }
            if (! Schema::hasColumn('brand_kits', 'cta_phrases')) {
                $table->json('cta_phrases')->nullable()->after('tone');
            }
            if (! Schema::hasColumn('brand_kits', 'banned_words')) {
                $table->json('banned_words')->nullable()->after('cta_phrases');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brand_kits', function (Blueprint $table) {
            if (Schema::hasColumn('brand_kits', 'banned_words')) {
                $table->dropColumn('banned_words');
            }
            if (Schema::hasColumn('brand_kits', 'cta_phrases')) {
                $table->dropColumn('cta_phrases');
            }
            if (Schema::hasColumn('brand_kits', 'tone')) {
                $table->dropColumn('tone');
            }
            if (Schema::hasColumn('brand_kits', 'design_brief')) {
                $table->dropColumn('design_brief');
            }
        });
    }
};
