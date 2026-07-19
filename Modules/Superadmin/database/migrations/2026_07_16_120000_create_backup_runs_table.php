<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('running')->index(); // running|success|failed
            $table->string('triggered_by', 20)->default('schedule');   // schedule|manual
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('db_dump_bytes')->nullable();
            $table->unsignedBigInteger('files_bytes')->nullable();
            $table->string('remote_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
