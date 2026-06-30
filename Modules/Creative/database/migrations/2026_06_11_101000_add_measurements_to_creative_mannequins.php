<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creative_mannequins', function (Blueprint $table) {
            // Yüz tarifi (kimliğin en belirleyici parçası).
            $table->text('face')->nullable()->after('hair');
            // Vücut ölçüleri (cm). Prompt'a "boy/göğüs/bel/kalça" olarak işlenir.
            $table->unsignedSmallInteger('height_cm')->nullable()->after('face');
            $table->unsignedSmallInteger('bust_cm')->nullable()->after('height_cm');
            $table->unsignedSmallInteger('waist_cm')->nullable()->after('bust_cm');
            $table->unsignedSmallInteger('hips_cm')->nullable()->after('waist_cm');
        });
    }

    public function down(): void
    {
        Schema::table('creative_mannequins', function (Blueprint $table) {
            $table->dropColumn(['face', 'height_cm', 'bust_cm', 'waist_cm', 'hips_cm']);
        });
    }
};
