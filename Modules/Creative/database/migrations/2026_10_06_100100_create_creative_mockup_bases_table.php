<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap giydirme — kilitli baz görseller: kombinli manken, bir pozda. Ana kare
 * (derived_from_base_id null) ve ondan türetilen pozlar. Segmentasyon etiketi ile
 * bölge/önde kalan/ayakkabı maskeleri ve kırpma kutusu `masks` JSON'undadır.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creative_mockup_bases')) {
            return;
        }

        Schema::create('creative_mockup_bases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outfit_id')->constrained('creative_mockup_outfits')->cascadeOnDelete();
            $table->foreignId('pose_id')->constrained('creative_poses')->cascadeOnDelete();
            $table->foreignId('derived_from_base_id')->nullable()->constrained('creative_mockup_bases')->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->json('masks')->nullable();
            $table->json('qa')->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['outfit_id', 'pose_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_mockup_bases');
    }
};
