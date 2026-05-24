<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('care_instructions')->nullable()->after('name');
            $table->string('material', 191)->nullable()->after('care_instructions');
            $table->string('origin_country', 2)->default('TR')->after('material');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['care_instructions', 'material', 'origin_country']);
        });
    }
};
