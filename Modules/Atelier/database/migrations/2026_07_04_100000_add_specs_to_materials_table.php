<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $t) {
            $t->jsonb('specs')->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $t) {
            $t->dropColumn('specs');
        });
    }
};
