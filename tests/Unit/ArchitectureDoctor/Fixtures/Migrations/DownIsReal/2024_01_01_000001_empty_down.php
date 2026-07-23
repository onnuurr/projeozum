<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_gadgets', function ($table) {
            $table->id();
        });
    }

    public function down(): void
    {
        // TODO: geri alma yazılmadı
    }
};
