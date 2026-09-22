<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patterns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->nullable()->unique();
            $table->string('name');
            // Birincil filtre alanı (tulum/ceket/kapüşonlu...).
            $table->string('product_type')->index();
            // İç içe bedenler: "116-122-128-134" gibi tek metin (Faz 3'e kadar ayrılmaz).
            $table->string('size_range')->nullable();
            $table->string('vendor')->nullable();
            $table->string('collection')->nullable();
            // Ölçek kalibrasyonu: kontrol karesi doğrulandı mı, sapma kaç mm.
            $table->boolean('scale_verified')->default(false);
            $table->decimal('scale_deviation_mm', 6, 2)->nullable();
            // Geometri dosyada yaşar (Seviye 1); VT yalnızca aranabilir alanları tutar.
            $table->string('dxf_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('preview_image_path')->nullable();
            $table->enum('status', ['draft', 'approved', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('vendor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patterns');
    }
};
