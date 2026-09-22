<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pattern_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_id')->constrained('patterns')->cascadeOnDelete();
            // Esnek etiketleme — ileride filtre yelpazesini genişletmek için.
            $table->string('tag');
            $table->timestamps();

            $table->unique(['pattern_id', 'tag']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pattern_tags');
    }
};
