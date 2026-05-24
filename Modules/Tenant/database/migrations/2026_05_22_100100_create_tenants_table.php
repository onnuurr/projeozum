<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 191);
            $table->string('legal_name', 255)->nullable();
            $table->foreignId('tenant_type_id')
                ->nullable()
                ->constrained('tenant_types')
                ->nullOnDelete();

            // Vergi / yasal bilgiler
            $table->string('tax_number', 32)->nullable();
            $table->string('tax_office', 191)->nullable();

            // İletişim
            $table->string('email', 191)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('contact_person', 191)->nullable();
            $table->string('contact_phone', 32)->nullable();

            // Adres
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('country', 100)->nullable()->default('TR');
            $table->string('postal_code', 16)->nullable();

            // Ticari koşullar
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);

            // Durum
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('tenant_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
