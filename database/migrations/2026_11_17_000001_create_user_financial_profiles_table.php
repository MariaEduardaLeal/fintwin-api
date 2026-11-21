<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_financial_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('monthly_income', 15, 2)->default(0);
            $table->decimal('monthly_fixed_expenses', 15, 2)->default(0);
            $table->decimal('emergency_fund_target', 15, 2)->default(0);
            $table->string('risk_profile', 32)->default('moderate');
            $table->unsignedSmallInteger('investment_horizon_months')->default(60);
            $table->string('ai_strictness_level', 32)->default('balanced');
            $table->json('financial_goals')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_financial_profiles');
    }
};
