<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pluggy_transaction_id')->nullable()->unique();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->string('type', 32);
            $table->date('transaction_date');
            $table->string('status', 32)->default('posted');
            $table->string('payment_method', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'transaction_date'], 'idx_tx_user_date');
            $table->index(['bank_account_id', 'transaction_date'], 'idx_tx_account_date');
            $table->index(['user_id', 'type', 'transaction_date'], 'idx_tx_user_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
