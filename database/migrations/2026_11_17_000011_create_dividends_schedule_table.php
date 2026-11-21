<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dividends_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('dividend');
            $table->decimal('amount_per_share', 15, 6);
            $table->date('ex_date')->nullable();
            $table->date('payment_date');
            $table->string('status', 32)->default('scheduled');
            $table->string('source', 32)->default('brapi');
            $table->timestamps();

            $table->unique(
                ['asset_id', 'type', 'payment_date', 'amount_per_share'],
                'uniq_div_asset_type_date_amt'
            );
            $table->index(['payment_date', 'status'], 'idx_div_payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dividends_schedule');
    }
};
