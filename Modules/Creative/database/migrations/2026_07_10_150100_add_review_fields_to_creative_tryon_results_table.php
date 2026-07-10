<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (! Schema::hasColumn('creative_tryon_results', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('creative_tryon_results', 'staged_image_path')) {
                // Üretilen ama henüz onaylanmamış çıktı; onay anına kadar product_images'a
                // hiç yazılmaz, sadece burada bekler (bkz. ProductOnModelService::publish).
                $table->string('staged_image_path', 500)->nullable()->after('product_image_id');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'review_status')) {
                // Üretimden bağımsız insan onayı: pending|approved|rejected.
                $table->string('review_status', 20)->nullable()->after('status');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'review_note')) {
                $table->text('review_note')->nullable()->after('review_status');
            }
            if (! Schema::hasColumn('creative_tryon_results', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('review_note')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('creative_tryon_results', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->index('review_status');
        });

        // Bu özellikten önce üretilmiş (zaten product_images'a yazılmış) sonuçlar
        // geriye dönük "onaylı" sayılır — mevcut mağaza görselleri kaybolmaz.
        DB::table('creative_tryon_results')
            ->where('status', 'done')
            ->whereNull('review_status')
            ->update(['review_status' => 'approved', 'reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            if (Schema::hasColumn('creative_tryon_results', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('creative_tryon_results', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('creative_tryon_results', 'review_note')) {
                $table->dropColumn('review_note');
            }
            if (Schema::hasColumn('creative_tryon_results', 'review_status')) {
                $table->dropIndex(['review_status']);
                $table->dropColumn('review_status');
            }
            if (Schema::hasColumn('creative_tryon_results', 'staged_image_path')) {
                $table->dropColumn('staged_image_path');
            }
            if (Schema::hasColumn('creative_tryon_results', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
