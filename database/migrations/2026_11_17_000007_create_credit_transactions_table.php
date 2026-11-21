<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pluggy_transaction_id')->nullable()->index();
            $table->string('installment_group_id', 36)->nullable()->index();
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->decimal('total_purchase_amount', 15, 2)->nullable();
            $table->unsignedSmallInteger('installment_number')->default(1);
            $table->unsignedSmallInteger('total_installments')->default(1);
            $table->date('purchase_date');
            $table->date('billing_date');
            $table->boolean('is_projected')->default(false);
            $table->timestamps();

            $table->unique(
                ['credit_card_id', 'pluggy_transaction_id', 'installment_number'],
                'uniq_ct_card_pluggy_inst'
            );
            $table->index(['user_id', 'billing_date'], 'idx_ct_user_billing_date');
            $table->index(['invoice_id', 'billing_date'], 'idx_ct_invoice_billing_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
