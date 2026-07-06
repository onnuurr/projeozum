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
            // Phase 4 XML feed token-based auth için tenant başına rotatable secret.
            // Tenant create observer'ı yeni kayıtlarda otomatik dolduracak (TenantService::create).
            $table->string('feed_secret', 64)->nullable()->after('settings');
        });

        // Backfill: mevcut tenant'lar için secret üret.
        DB::table('tenants')->whereNull('feed_secret')->eachById(function ($tenant) {
            DB::table('tenants')->where('id', $tenant->id)->update([
                'feed_secret' => Str::random(64),
            ]);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('feed_secret', 64)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['feed_secret']);
            $table->dropColumn('feed_secret');
        });
    }
};
