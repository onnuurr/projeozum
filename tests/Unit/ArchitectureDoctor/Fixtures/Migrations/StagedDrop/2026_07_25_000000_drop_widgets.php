<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixture_widgets_deprecated_20260601');
    }

    public function down(): void
    {
        //
    }
};
