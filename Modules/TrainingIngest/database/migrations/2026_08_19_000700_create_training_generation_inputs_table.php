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

        Schema::connection($this->connection)->create('generation_inputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generation_id')->constrained('generations')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->integer('position');
            $table->text('input_kind');

            $table->unique(['generation_id', 'position']);
        });

        DB::connection($this->connection)->statement(
            "ALTER TABLE generation_inputs ADD CONSTRAINT generation_inputs_input_kind_check ".
            "CHECK (input_kind IN ('front','back','side','seam','collar','detail','mannequin','other'))"
        );
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('generation_inputs');
    }
};
