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

        Schema::connection($this->connection)->create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // SaaS tarafındaki creative_tryon_results.generation_token — idempotanlık anahtarı.
            // TryonResult satırı yerinde güncellendiği (updateOrCreate) için üretim id'si
            // değil, her denemede yenilenen bu token kullanılır.
            $table->text('source_generation_id')->unique();

            $table->foreignUuid('mannequin_id')->nullable()->constrained('mannequins')->restrictOnDelete();
            $table->foreignUuid('pose_id')->nullable()->constrained('poses')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('prompt_version_id')->constrained('prompt_versions')->restrictOnDelete();

            $table->text('rendered_prompt');
            $table->text('model_provider');
            $table->text('model_name');
            $table->jsonb('model_params')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->timestampTz('requested_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index([DB::raw('requested_at DESC')], 'generations_requested_at_desc_idx');
            $table->index('product_id');
            $table->index('prompt_version_id');
        });
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('generations');
    }
};
