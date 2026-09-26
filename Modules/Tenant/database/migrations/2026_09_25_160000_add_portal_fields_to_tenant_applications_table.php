<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagisto bayi portalından (ozmbayi teması) gelen başvurular için:
 * - public_token: başvuru sahibinin durum/eksik evrak sayfasına erişim anahtarı (giriş yok)
 * - source: 'web' (SaaS /tenant-basvuru) | 'bagisto'
 * - details: portal formunun ek adımları (şirket türü, bayilik modeli, kanallar, hacim, IBAN, onaylar)
 * - missing_*: admin'in "Eksik evrak iste" aksiyonu
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->string('public_token', 64)->nullable()->unique()->after('id');
            $table->string('source', 20)->default('web')->after('status');
            $table->json('details')->nullable()->after('message');
            $table->json('missing_documents')->nullable()->after('rejection_reason');
            $table->text('missing_note')->nullable()->after('missing_documents');
            $table->timestamp('missing_requested_at')->nullable()->after('missing_note');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['public_token', 'source', 'details', 'missing_documents', 'missing_note', 'missing_requested_at']);
        });
    }
};
