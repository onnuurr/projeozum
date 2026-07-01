<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->string('public_name', 255)->nullable()->after('slug');
            $t->text('public_description')->nullable()->after('public_name');
            $t->text('tenant_description')->nullable()->after('public_description');
            $t->timestamp('ai_generated_at')->nullable()->after('tenant_description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropColumn(['public_name', 'public_description', 'tenant_description', 'ai_generated_at']);
        });
    }
};
