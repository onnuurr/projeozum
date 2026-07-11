<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ret anında seçilen "düzeltilmesi gereken alan" maddelerinin (label snapshot'ları)
 * manken ve tryon sonuçlarına eklenmesi. Açıklama (review_note) yanında saklanır;
 * seçenek daha sonra silinse bile geçmiş retteki seçim okunabilir kalsın diye
 * madde id'si değil, o anki etiket dizisi (JSON) tutulur.
 */
return new class extends Migration
{
    private const TABLES = ['creative_mannequins', 'creative_tryon_results'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasColumn($name, 'review_tags')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->json('review_tags')->nullable()->after('review_note');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'review_tags')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('review_tags');
                });
            }
        }
    }
};
