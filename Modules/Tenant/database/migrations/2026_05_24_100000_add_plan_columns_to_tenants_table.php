<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug', 191)->nullable()->after('name');
            $table->string('logo_path', 500)->nullable()->after('postal_code');
            $table->json('settings')->nullable()->after('logo_path');
            $table->foreignId('created_by')->nullable()->after('settings')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('tenants')->whereNull('slug')->eachById(function ($tenant) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'slug' => Str::slug($tenant->name) . '-' . $tenant->id,
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('slug', 191)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'logo_path', 'settings', 'created_by']);
        });
    }
};
