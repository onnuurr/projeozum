<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi portalı başvurusu iki aşamalı (2026-09-26):
 * 1) Ön başvuru (firma + satış kanalları) → görüşme sonrası admin resmi belge aşamasını açar
 *    (documents_requested_at) → bayi belge/banka/sözleşmeyi yükler (documents_submitted_at).
 * 2) Resmi evrak onayı → tenant pasif açılır, bayiye üyelik tamamlama daveti gider
 *    (invitation_*) → bayi parolasını ve eksik bilgilerini girince tenant aktifleşir (completed_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->timestamp('documents_requested_at')->nullable()->after('missing_requested_at');
            $table->timestamp('documents_submitted_at')->nullable()->after('documents_requested_at');
            $table->string('invitation_token', 64)->nullable()->unique()->after('documents_submitted_at');
            $table->timestamp('invitation_sent_at')->nullable()->after('invitation_token');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_sent_at');
            $table->timestamp('completed_at')->nullable()->after('invitation_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropUnique(['invitation_token']);
            $table->dropColumn([
                'documents_requested_at', 'documents_submitted_at', 'invitation_token',
                'invitation_sent_at', 'invitation_expires_at', 'completed_at',
            ]);
        });
    }
};
