<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creative_review_chats', function (Blueprint $table) {
            $table->id();
            // Reddedilen Mannequin|TryonResult kaydı (polymorphic).
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            // Konuşan kişi: üretici hesap veya creative.approve sahibi bir yönetici.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // user|assistant.
            $table->string('role', 20);
            $table->text('content');
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'creative_review_chats_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creative_review_chats');
    }
};
