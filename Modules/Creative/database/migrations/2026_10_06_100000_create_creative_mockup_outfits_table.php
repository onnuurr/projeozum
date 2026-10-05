<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mokap giydirme — ürüne özel kombin (bkz. docs/superpowers/specs/2026-10-05-creative-mokap-giydirme-design.md).
 * Bir ürün × manken için stilistin kurduğu kombin; o ürünün bütün pozlarında aynıdır.
 * region: bottom|top|one_piece|set ; length: mini|knee|midi|maxi ; length_source: attribute|ai.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('creative_mockup_outfits')) {
            return;
        }

        Schema::create('creative_mockup_outfits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('mannequin_id')->constrained('creative_mannequins')->cascadeOnDelete();
            $table->string('region', 20);
            $table->string('length', 10);
            $table->string('length_source', 10);
            $table->json('pieces')->nullable();
            $table->text('outfit_sentence')->nullable();
            $table->json('poses')->nullable();
            $table->text('reasoning_tr')->nullable();
            $table->unsignedSmallInteger('stylist_prompt_version')->default(1);
            $table->string('status', 20)->default('queued')->index();
            $table->text('error')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'mannequin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_mockup_outfits');
    }
};
