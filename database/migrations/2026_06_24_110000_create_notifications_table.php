<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel DB notification tablosu. Çalışan Postgres'te zaten elle mevcut
 * (repo migration'ı yoktu); bu migration onu KAYIT ALTINA alır ve test/yeni
 * ortamlarda (sqlite) oluşturur. hasTable guard'ı prod'daki mevcut tabloyu korur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return; // prod'da zaten var — dokunma
        }

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Paylaşılan framework altyapısı; geri alımda DROP etmiyoruz (prod'da
        // bu migration'dan önce de vardı). Bilinçli no-op.
    }
};
