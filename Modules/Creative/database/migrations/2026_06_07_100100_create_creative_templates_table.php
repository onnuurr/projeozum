<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('svg_path', 500);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            // Parse edilmiş slot tanımları: [{key,type,x,y,w,h,fit,font_size,align,...}]
            $table->json('slots')->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_templates');
    }
};
