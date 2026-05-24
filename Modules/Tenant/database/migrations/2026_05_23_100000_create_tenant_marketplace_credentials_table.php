<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_marketplace_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Hedef pazaryeri: trendyol / hepsiburada / n11 / ciceksepeti
            $table->string('marketplace', 32);
            // Mağaza/satıcı kimliği (Trendyol supplierId, Hepsiburada merchantId, vb.)
            $table->string('supplier_id', 64)->nullable();
            $table->string('store_name', 191)->nullable();
            // Şifrelenmiş kimlik bilgileri — model'de encrypted cast ile yazılır/okunur.
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'marketplace'], 'uq_tenant_marketplace');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_marketplace_credentials');
    }
};
