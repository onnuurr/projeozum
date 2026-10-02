<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resmi belge adımında bekleyen bayiye admin'in gönderdiği son hatırlatma e-postasının zamanı
 * (başvuru ayrıntısında gösterilir, art arda gönderimi sınırlar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->timestamp('documents_reminded_at')->nullable()->after('documents_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_applications', function (Blueprint $table) {
            $table->dropColumn('documents_reminded_at');
        });
    }
};
