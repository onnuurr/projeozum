<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi sözleşmesi metin biçimi: zengin metin editörüyle yazılan yeni sürümler/taslaklar 'html'
 * (sunucuda HTMLPurifier ile süzülür), önceki sürümler 'markdown' kalır ve öyle çizilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->string('format', 16)->default('markdown')->after('body');
        });

        Schema::table('dealer_agreement_drafts', function (Blueprint $table) {
            $table->string('format', 16)->default('markdown')->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('dealer_agreement_drafts', function (Blueprint $table) {
            $table->dropColumn('format');
        });

        Schema::table('dealer_agreement_versions', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
