<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('legal_name', 255)->nullable();
            $table->string('tax_number', 32);
            $table->string('tax_office', 191)->nullable();

            $table->string('contact_person', 191);
            $table->string('contact_email', 191);
            $table->string('contact_phone', 32)->nullable();

            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('country', 100)->nullable()->default('TR');
            $table->string('postal_code', 16)->nullable();

            $table->text('message')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('converted_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_applications');
    }
};
