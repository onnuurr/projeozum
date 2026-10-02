<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi Sözleşmeleri: bayi portalında (Bagisto) onaylatılan sözleşme ve onay metinleri.
 * Metinler SaaS'tan yönetilir, Bagisto API ile okur. Her yayın yeni bir satır
 * (dealer_agreement_versions) olarak eklenir ve bir daha değiştirilmez — bayinin onayladığı
 * sürüm numarası başvuru kaydında saklandığı için o sürümün metni her zaman geri okunabilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('title');
            // document: kaydırılabilir kutuda / ayrı sayfada gösterilen uzun metin;
            // consent: onay kutusunun yanında tek paragraf olarak gösterilen metin.
            $table->string('display', 16)->default('document');
            $table->string('description', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('dealer_agreement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_agreement_id')->constrained('dealer_agreements')->cascadeOnDelete();
            $table->string('version', 32);
            $table->string('title');
            $table->longText('body');
            $table->string('change_note', 500)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['dealer_agreement_id', 'version']);
            $table->index(['dealer_agreement_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_agreement_versions');
        Schema::dropIfExists('dealer_agreements');
    }
};
