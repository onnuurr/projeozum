<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

        Schema::connection($this->connection)->create('generation_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generation_id')->constrained('generations')->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained('assets')->restrictOnDelete();
            $table->integer('variant_index')->default(0);

            $table->unique(['generation_id', 'variant_index']);
        });
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('generation_outputs');
    }
};
