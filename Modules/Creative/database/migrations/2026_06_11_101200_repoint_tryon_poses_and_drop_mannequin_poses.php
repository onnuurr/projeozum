<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pose_id artık bağımsız creative_poses kütüphanesine işaret eder.
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropForeign(['pose_id']);
        });
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->foreign('pose_id')->references('id')->on('creative_poses')->nullOnDelete();
        });

        // Mankene bağlı poz tablosu artık kullanılmıyor (pozlar bağımsız).
        Schema::dropIfExists('creative_mannequin_poses');
    }

    public function down(): void
    {
        // Eski mankene-bağlı poz tablosunu geri oluştur.
        Schema::create('creative_mannequin_poses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mannequin_id')
                ->constrained('creative_mannequins')
                ->cascadeOnDelete();
            $table->string('pose_key', 60);
            $table->string('label', 120)->nullable();
            $table->text('pose_prompt')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('queued');
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['mannequin_id', 'sort_order']);
        });

        // pose_id FK'sını eski tabloya geri bağla.
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->dropForeign(['pose_id']);
        });
        Schema::table('creative_tryon_results', function (Blueprint $table) {
            $table->foreign('pose_id')->references('id')->on('creative_mannequin_poses')->nullOnDelete();
        });
    }
};
