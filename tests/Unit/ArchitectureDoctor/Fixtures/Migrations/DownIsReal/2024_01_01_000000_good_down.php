<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_widgets', function ($table) {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_widgets');
    }
};
