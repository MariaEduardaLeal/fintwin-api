<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();
            $table->string('pluggy_bill_id')->nullable()->unique();
            $table->string('reference_month', 7);
            $table->date('closing_date');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 32)->default('open');
            $table->timestamps();

            $table->unique(['credit_card_id', 'reference_month'], 'uniq_invoices_card_month');
            $table->index(['user_id', 'reference_month', 'status'], 'idx_invoices_user_month_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
