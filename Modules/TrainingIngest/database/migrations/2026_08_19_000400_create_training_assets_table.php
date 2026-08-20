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

        Schema::connection($this->connection)->create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('storage_bucket');
            $table->text('storage_key');
            $table->char('content_sha256', 64)->unique();
            $table->text('mime_type');
            $table->bigInteger('byte_size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->text('role');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['storage_bucket', 'storage_key']);
        });

        DB::connection($this->connection)->statement(
            "ALTER TABLE assets ADD CONSTRAINT assets_role_check ".
            "CHECK (role IN ('product_input','mannequin_input','generated_output'))"
        );
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('assets');
    }
};
