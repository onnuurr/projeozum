<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('iban')->unique();
            $table->string('currency', 3)->default('TRY');
            $table->string('account_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finance_bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('finance_bank_accounts')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('format', 16); // mt940|csv|xlsx
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('imported_row_count')->default(0);
            $table->text('error_message')->nullable();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('finance_bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')
                ->constrained('finance_bank_statement_imports')
                ->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained('finance_bank_accounts')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            // Pozitif = giriş, negatif = çıkış (tek kolon convention).
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TRY');
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('reconciliation_status', 16)->default('unmatched'); // unmatched|matched|ignored
            $table->string('matched_invoice_type', 32)->nullable(); // supplier_invoice|outgoing_invoice|tenant_invoice|order
            $table->unsignedBigInteger('matched_invoice_id')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reconciliation_status']);
            $table->index(['matched_invoice_type', 'matched_invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_bank_transactions');
        Schema::dropIfExists('finance_bank_statement_imports');
        Schema::dropIfExists('finance_bank_accounts');
    }
};
