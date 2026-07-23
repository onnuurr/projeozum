<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixture_widgets')) {
            Schema::rename('fixture_widgets', 'fixture_widgets_deprecated_20260601');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fixture_widgets_deprecated_20260601')) {
            Schema::rename('fixture_widgets_deprecated_20260601', 'fixture_widgets');
        }
    }
};
