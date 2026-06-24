<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversion_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('source_pdf_path');
            $table->string('output_dxf_path')->nullable();
            // Sınıflandırma: yeşil (otomatik), sarı (operatör seçer), kırmızı (sorunlu).
            $table->enum('classification', ['green', 'yellow', 'red'])->nullable()->index();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->enum('status', ['pending', 'processing', 'needs_review', 'approved', 'rejected', 'failed'])
                ->default('pending');
            // Yapısal hata raporu (bozuk ızgara, renk şeması, eksik parça vb.).
            $table->json('error_report')->nullable();
            // Onaylanınca üretilen kalıp kaydına bağlanır.
            $table->foreignId('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_jobs');
    }
};
