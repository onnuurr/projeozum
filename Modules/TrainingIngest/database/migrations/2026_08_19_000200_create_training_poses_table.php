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

        Schema::connection($this->connection)->create('poses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('external_id')->unique();
            $table->text('label')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('poses');
    }
};
