<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atelier_assignments', function (Blueprint $table) {
            $table->id();
            // Tasarım kartı ve/veya kalıp; en az biri dolu olur (controller doğrular).
            $table->foreignId('design_card_id')->nullable()->constrained('design_cards')->nullOnDelete();
            $table->foreignId('pattern_id')->nullable()->constrained('patterns')->nullOnDelete();
            // İşin niteliği: kalıpçı / tasarımcı.
            $table->enum('kind', ['pattern_maker', 'designer'])->default('pattern_maker');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('due_date')->nullable();
            // İş akışı: atandı → başladı → teslim → kabul/ret.
            $table->enum('status', ['pending', 'in_progress', 'delivered', 'accepted', 'rejected'])->default('pending');
            // Kalıpçının teslim ettiği dosya (revize DXF/PDF vb.).
            $table->string('delivered_file_path')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('assigned_to');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atelier_assignments');
    }
};
