<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * İş ortağı paylaşımlarında (partner_share kanalı) markanın kendi BrandKit'ine
 * EK olarak uygulanan, partner'a özel kreatif bilgiler — bkz. Creative Rules
 * Engine tasarımı: BrandKit + PartnerBrandKit + CreativeTemplate + Product
 * → Rules Engine → Creative Specification → Prompt Builder → AI.
 *
 * PartnerBrandKit ana BrandKit'in yerini almaz, üstüne eklenir — marka
 * kimliğinin (renk/logo/ton) temel kuralları bozulmaz, sadece partner'a özgü
 * ek öğeler (ikinci logo, iletişim bilgisi, özel fiyat, partner CTA'sı)
 * kreatif spesifikasyona dahil edilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_brand_kits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            // Partner logosu (marka logosunun YANINA eklenir, yerine geçmez).
            $table->string('logo_path')->nullable();
            // {"phone":"...", "email":"...", "website":"..."}
            $table->json('contact')->nullable();
            // Partnere özel CTA metinleri — markanın kendi cta_phrases'inden
            // farklı olabilir (ör. "Bu bayiden satın al").
            $table->json('cta_phrases')->nullable();
            // Partnere özel fiyat gösterimi: {"enabled":bool,"note":"..."}
            $table->json('price_display')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_brand_kits');
    }
};
