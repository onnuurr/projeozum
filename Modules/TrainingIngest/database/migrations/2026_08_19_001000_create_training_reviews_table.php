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

        Schema::connection($this->connection)->create('reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('generation_output_id')->constrained('generation_outputs')->restrictOnDelete();
            $table->text('verdict');
            $table->text('reason_code')->nullable();
            $table->text('reason_text')->nullable();
            $table->smallInteger('severity')->nullable();
            $table->text('reviewer');
            $table->timestampTz('reviewed_at');
            $table->uuid('superseded_by')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign('reason_code')->references('code')->on('rejection_reasons')->restrictOnDelete();

            $table->index('verdict');
            $table->index('reason_code');
            $table->index([DB::raw('reviewed_at DESC')], 'reviews_reviewed_at_desc_idx');
        });

        // Kendi tablosuna referans veren FK, ayrı bir Schema::table() çağrısında eklenir:
        // create() closure'ı bittikten sonra fluent ->primary() komutu (id sütunu) devreye
        // giriyor — aynı closure içinde tanımlanan bir self-referencing FK, primary key
        // henüz eklenmeden çalışıp "no unique constraint matching" hatası verirdi.
        Schema::connection($this->connection)->table('reviews', function (Blueprint $table) {
            $table->foreign('superseded_by')->references('id')->on('reviews')->nullOnDelete();
        });

        DB::connection($this->connection)->statement(
            "ALTER TABLE reviews ADD CONSTRAINT reviews_verdict_check ".
            "CHECK (verdict IN ('approved','rejected'))"
        );
        DB::connection($this->connection)->statement(
            "ALTER TABLE reviews ADD CONSTRAINT reviews_severity_check ".
            "CHECK (severity IS NULL OR severity BETWEEN 1 AND 5)"
        );
        DB::connection($this->connection)->statement(
            "ALTER TABLE reviews ADD CONSTRAINT reviews_rejected_requires_reason_check ".
            "CHECK (verdict <> 'rejected' OR reason_code IS NOT NULL)"
        );
    }

    public function down(): void
    {
        if (! $this->runsOnPostgres()) {
            return;
        }

        Schema::connection($this->connection)->dropIfExists('reviews');
    }
};
