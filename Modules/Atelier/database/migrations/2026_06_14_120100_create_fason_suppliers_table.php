<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fason_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('contact_name', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->string('tax_no', 32)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fason_suppliers');
    }
};
