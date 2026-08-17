<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('architecture_doctor_fix_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('queued')->index(); // queued|running|success|failed
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('branch_name')->nullable();
            $table->string('base_commit_sha', 40)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('rules_before')->nullable();
            $table->json('rules_after')->nullable();
            $table->json('files_changed')->nullable();
            $table->longText('log_output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('architecture_doctor_fix_runs');
    }
};
