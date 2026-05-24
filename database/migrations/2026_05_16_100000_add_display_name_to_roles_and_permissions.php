<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropColumn('display_name');
        });

        Schema::table($tableNames['permissions'], function (Blueprint $table) {
            $table->dropColumn('display_name');
        });
    }
};
