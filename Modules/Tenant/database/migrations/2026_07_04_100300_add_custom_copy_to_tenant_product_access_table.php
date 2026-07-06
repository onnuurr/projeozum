<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_product_access', function (Blueprint $t) {
            $t->string('custom_name', 255)->nullable()->after('notes');
            $t->text('custom_description')->nullable()->after('custom_name');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_product_access', function (Blueprint $t) {
            $t->dropColumn(['custom_name', 'custom_description']);
        });
    }
};
