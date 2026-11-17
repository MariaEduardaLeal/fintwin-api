<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 18, 8)->default(0);
            $table->decimal('average_price', 15, 4)->default(0);
            $table->decimal('invested_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['portfolio_id', 'asset_id'], 'uniq_portfolio_asset');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_assets');
    }
};
