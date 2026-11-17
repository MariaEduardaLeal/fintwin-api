<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('pluggy_category_id')->nullable()->index();
            $table->string('name');
            $table->string('slug');
            $table->string('type', 32)->default('expense');
            $table->string('color', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_essential')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'slug'], 'uniq_categories_user_slug');
            $table->index(['type', 'is_essential'], 'idx_categories_type_essential');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
