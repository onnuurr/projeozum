<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TrainingIngest\Database\Migrations\Concerns\SkipsNonPostgres;

return new class extends Migration
{
    use SkipsNonPostgres;

    protected $connection = 'training';

    public function up(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->create('ingestion_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('source_generation_id');
            $table->text('status');
            $table->text('error_detail')->nullable();
            $table->integer('attempt_count')->default(1);
            $table->jsonb('payload_snapshot')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index('source_generation_id');
            $table->index('status');
        });

        DB::connection($this->connection)->statement(
            "ALTER TABLE ingestion_log ADD CONSTRAINT ingestion_log_status_check ".
            "CHECK (status IN ('success','skipped','failed'))"
        );
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('ingestion_log');
    }
};
