<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kalıp Kartı üretim verisi (2026-10-07):
 *  - patterns: kalıp tipi bağı, beden listesi, model varyantları (Burda A/B), malzeme, dikiş/etek payı
 *  - pattern_parts: parça no, varyant, kumaş kodu, not (PDF parça listesi: "6 Kol 2x")
 *  - pattern_measurements: beden × ölçü noktası değerleri (vücut + hazır giysi), sorgulanabilir
 *
 * patterns.fabric_usage JSON kolonu zaten var; yeni yapı model/servis katmanında.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patterns', function (Blueprint $table) {
            if (! Schema::hasColumn('patterns', 'pattern_type_id')) {
                $table->foreignId('pattern_type_id')->nullable()->after('product_type')
                    ->constrained('pattern_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('patterns', 'sizes')) {
                $table->json('sizes')->nullable()->after('size_range');
            }
            if (! Schema::hasColumn('patterns', 'variants')) {
                $table->json('variants')->nullable()->after('sizes');
            }
            if (! Schema::hasColumn('patterns', 'materials')) {
                $table->json('materials')->nullable()->after('fabric_usage');
            }
            if (! Schema::hasColumn('patterns', 'seam_allowance_cm')) {
                $table->decimal('seam_allowance_cm', 4, 2)->nullable()->after('materials');
            }
            if (! Schema::hasColumn('patterns', 'hem_allowance_cm')) {
                $table->decimal('hem_allowance_cm', 4, 2)->nullable()->after('seam_allowance_cm');
            }
        });

        Schema::table('pattern_parts', function (Blueprint $table) {
            if (! Schema::hasColumn('pattern_parts', 'part_no')) {
                $table->string('part_no', 16)->nullable()->after('pattern_id');
            }
            if (! Schema::hasColumn('pattern_parts', 'variants')) {
                $table->string('variants', 50)->nullable()->after('size_range');
            }
            if (! Schema::hasColumn('pattern_parts', 'fabric_code')) {
                $table->string('fabric_code', 16)->nullable()->after('variants');
            }
            if (! Schema::hasColumn('pattern_parts', 'notes')) {
                $table->string('notes')->nullable()->after('fabric_code');
            }
        });

        if (! Schema::hasTable('pattern_measurements')) {
            Schema::create('pattern_measurements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pattern_id')->constrained('patterns')->cascadeOnDelete();
                // Şablon/sözlük noktası; null ise custom_label'lı serbest satır.
                $table->foreignId('measurement_point_id')->nullable()
                    ->constrained('pattern_measurement_points')->nullOnDelete();
                $table->string('custom_label', 100)->nullable();
                $table->string('basis', 16); // body | garment
                // Aynı kalıpta birden fazla tablo (ör. Burda Kız/Erkek vücut tablosu).
                $table->string('group_label', 50)->nullable();
                $table->string('size_label', 32);
                $table->decimal('value', 7, 2);
                $table->string('source', 16)->default('manual'); // pdf | manual
                $table->timestamps();

                $table->index(['pattern_id', 'basis']);
                $table->index(['measurement_point_id', 'size_label']);
            });
        }

        // Mevcut serbest-metin ürün tiplerini yeni tip listesine bağla.
        foreach (DB::table('pattern_types')->pluck('id', 'name') as $name => $id) {
            DB::table('patterns')->where('product_type', $name)->whereNull('pattern_type_id')
                ->update(['pattern_type_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_measurements');

        Schema::table('pattern_parts', function (Blueprint $table) {
            foreach (['part_no', 'variants', 'fabric_code', 'notes'] as $col) {
                if (Schema::hasColumn('pattern_parts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('patterns', function (Blueprint $table) {
            if (Schema::hasColumn('patterns', 'pattern_type_id')) {
                $table->dropConstrainedForeignId('pattern_type_id');
            }
        });
        Schema::table('patterns', function (Blueprint $table) {
            foreach (['sizes', 'variants', 'materials', 'seam_allowance_cm', 'hem_allowance_cm'] as $col) {
                if (Schema::hasColumn('patterns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
