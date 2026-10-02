<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi sözleşmesi onay arşivi: bayi bir sözleşmeyi/onay metnini kabul ettiği anda, o sürümün metni
 * bayinin bilgileriyle ({{company.legal_name}} vb. etiketler doldurulmuş) HTML olarak saklanır.
 * Sonradan değişmez; yazdırılabilir kopya (Bayi Sözleşmeleri › onay çıktısı) buradan üretilir.
 * Başvuru/iş ortağı silinse de hukuki kayıt korunur (FK null'a çekilir, firma bilgisi `data`da durur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_agreement_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_agreement_version_id')->nullable()->constrained('dealer_agreement_versions')->nullOnDelete();
            $table->foreignId('tenant_application_id')->nullable()->constrained('tenant_applications')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('agreement_key', 64);
            $table->string('version', 32);
            // application (ön başvuru), official (resmi belge adımı), membership (üyelik tamamlama)
            $table->string('context', 32);
            $table->string('title');
            $table->longText('html');
            $table->json('data');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['tenant_application_id', 'accepted_at']);
            $table->index(['tenant_id', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_agreement_acceptances');
    }
};
