<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixture_legacy_sprockets');
    }

    public function down(): void
    {
        //
    }
};
