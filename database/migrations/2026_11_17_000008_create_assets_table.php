<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 20)->unique();
            $table->string('name');
            $table->string('asset_class', 32);
            $table->string('currency', 3)->default('BRL');
            $table->string('sector', 100)->nullable();
            $table->decimal('current_price', 15, 4)->default(0);
            $table->timestamp('price_updated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('asset_class', 'idx_assets_class');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
