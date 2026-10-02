<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sözleşme ekleri (Ek-1, Ek-2 …): ek, ana sözleşmeye bağlı bir alt sözleşmedir (parent_id) ve kendi
 * sürüm/dil/taslak serisine sahiptir — ana metinden bağımsız güncellenir. annex_number ana sözleşme içinde
 * tekildir ve yeniden kullanılmaz; kaldırılan ek is_active = false olur (onay arşivi ve geçmiş sürümler korunur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dealer_agreements', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('dealer_agreements')->restrictOnDelete();
            $table->unsignedSmallInteger('annex_number')->nullable()->after('parent_id');
            $table->boolean('is_active')->default(true)->after('sort_order');

            $table->unique(['parent_id', 'annex_number']);
        });
    }

    public function down(): void
    {
        // Ek kayıtları (ve sürümleri/taslakları, cascade) atılır; ana sözleşmeler korunur.
        \Illuminate\Support\Facades\DB::table('dealer_agreements')->whereNotNull('parent_id')->delete();

        Schema::table('dealer_agreements', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'annex_number']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['annex_number', 'is_active']);
        });
    }
};
