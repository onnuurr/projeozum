<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bayi sözleşmesi taslakları: yayımlamadan önce sunucuda saklanan çalışma metni. Sözleşme + dil başına
 * tek taslak (üzerine yazılır); yayımlanınca silinir. Bayi portalına gitmez, sürüm numarası almaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealer_agreement_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dealer_agreement_id')->constrained('dealer_agreements')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->string('change_note', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dealer_agreement_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealer_agreement_drafts');
    }
};
