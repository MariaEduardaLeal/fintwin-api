<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->json('target_allocation')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_primary'], 'idx_portfolios_user_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolios');
    }
};
