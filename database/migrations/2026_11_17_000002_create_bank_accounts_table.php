<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('pluggy_item_id')->nullable()->index();
            $table->string('pluggy_account_id')->nullable()->unique();
            $table->string('institution_name');
            $table->string('name');
            $table->string('type', 32);
            $table->string('subtype', 64)->nullable();
            $table->string('number', 32)->nullable();
            $table->string('currency_code', 3)->default('BRL');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type'], 'idx_bank_accounts_user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
