<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pattern_engine_jobs', function (Blueprint $table) {
            // Doluysa bu iş bir gradasyon talebidir (§4.5, Sprint 3): `size` taban
            // bedendir, `graded_sizes` ek hedef bedenlerin listesidir. Boşsa (null)
            // eski tekil-üretim davranışı değişmeden sürer.
            $table->json('graded_sizes')->nullable()->after('size');
        });
    }

    public function down(): void
    {
        Schema::table('pattern_engine_jobs', function (Blueprint $table) {
            $table->dropColumn('graded_sizes');
        });
    }
};
