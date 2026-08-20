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

        Schema::connection($this->connection)->create('prompt_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('version_label')->unique();
            $table->text('template_text');
            $table->char('template_sha256', 64)->unique();
            $table->text('changelog')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('prompt_versions');
    }
};
