<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_application_id')->constrained('tenant_applications')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('path', 255);
            $table->string('original_filename', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();

            $table->index('tenant_application_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_application_documents');
    }
};
