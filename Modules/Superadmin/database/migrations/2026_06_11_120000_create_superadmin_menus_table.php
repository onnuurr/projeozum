<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superadmin_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('superadmin_menus')->cascadeOnDelete();
            $table->string('label', 100);
            $table->string('icon', 64)->nullable();
            $table->string('route_name', 150)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('permission', 150)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superadmin_menus');
    }
};
