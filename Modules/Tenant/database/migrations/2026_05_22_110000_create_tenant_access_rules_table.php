<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // scope_type: 'brand' veya 'category'. Hangi tabloya işaret ettiği uygulamada çözülür.
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id');
            // is_blocked=true → bu marka/kategori bu tenant'a kapalı (blacklist mode).
            $table->boolean('is_blocked')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'scope_type', 'scope_id'], 'uq_tenant_scope');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_access_rules');
    }
};
