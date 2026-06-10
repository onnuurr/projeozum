<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Aynı anda tek bir kit varsayılan olur; render/AI/caption bunu kullanır.
            $table->boolean('is_default')->default(false);
            // Renk token'ları: {"primary":"#...","secondary":"#...","text":"#...",...}
            $table->json('palette')->nullable();
            // Tipografi: {"regular":"...ttf","bold":"...ttf","scale":{...}}
            $table->json('typography')->nullable();
            // Logo varyantları: {"primary":"path","mono":"path",...}
            $table->json('logos')->nullable();
            // Boşluk token'ları: {"sm":8,"md":16,"lg":32,...}
            $table->json('spacing')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_default');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_kits');
    }
};
