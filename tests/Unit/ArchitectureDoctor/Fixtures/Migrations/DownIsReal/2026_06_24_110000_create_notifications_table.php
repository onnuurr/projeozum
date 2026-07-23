<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function ($table) {
            $table->id();
        });
    }

    public function down(): void
    {
        // Bilinçli no-op — EXCLUDED_MIGRATIONS ile whitelist'e alındı.
    }
};
