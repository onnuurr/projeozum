<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * creative_mannequins/creative_tryon_results'taki ret-nedeni sistemiyle birebir
 * aynı review alan seti (bkz. 2026_07_10_150000_.../2026_07_11_120100_...) —
 * Creative Studio (creative_assets) tarafına da uygulanır. review_status zaten
 * var (create migration'ında), burada eklenmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_assets', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('template_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('creative_assets', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_status');
            }
            if (! Schema::hasColumn('creative_assets', 'review_tags')) {
                $table->json('review_tags')->nullable()->after('review_note');
            }
            if (! Schema::hasColumn('creative_assets', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('review_tags')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('creative_assets', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('creative_assets', function (Blueprint $table) {
            if (Schema::hasColumn('creative_assets', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('creative_assets', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('creative_assets', 'review_tags')) {
                $table->dropColumn('review_tags');
            }
            if (Schema::hasColumn('creative_assets', 'review_note')) {
                $table->dropColumn('review_note');
            }
            if (Schema::hasColumn('creative_assets', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
