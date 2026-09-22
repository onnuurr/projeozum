<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_cards', function (Blueprint $table) {
            // Asenkron üretim durumu: null=eski/senkron, processing/done/failed.
            $table->string('generation_status', 20)->nullable()->after('status');
            $table->text('generation_error')->nullable()->after('generation_status');
            // Üretim işinin tarifi yeniden kurabilmesi için saklanan istek parametreleri.
            $table->json('request_params')->nullable()->after('generation_error');
        });
    }

    public function down(): void
    {
        Schema::table('design_cards', function (Blueprint $table) {
            $table->dropColumn(['generation_status', 'generation_error', 'request_params']);
        });
    }
};
