<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_cards', function (Blueprint $table) {
            $table->id();
            // Akış X (önce kalıp seç) = pattern_first; Akış Y (önce AI konsept) = concept_first.
            $table->enum('source', ['pattern_first', 'concept_first']);
            $table->text('prompt')->nullable();
            $table->string('product_type')->nullable()->index();
            $table->string('target_size')->nullable();
            // Üretilen görsel yolları (AI çıktısı = piksel; geometri/ölçü içermez).
            $table->json('generated_images')->nullable();
            // X: kayıt anında dolu. Y: kullanıcı önerilen kalıbı seçince dolar.
            $table->foreignId('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            $table->enum('status', ['draft', 'generated', 'matched', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_cards');
    }
};
