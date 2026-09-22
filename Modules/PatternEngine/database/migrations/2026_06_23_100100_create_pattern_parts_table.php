<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->constrained('patterns')->cascadeOnDelete();
            // Parça adı: kol/ön/arka/kapüşon/yaka/manşet... (PDF metninden çıkarılır).
            $table->string('part_name');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('size_range')->nullable();
            $table->timestamps();

            $table->index('pattern_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_parts');
    }
};
