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
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('label');
            $table->bigInteger('amount_cents');
            $table->date('date');
            $table->boolean('reconciled')->default(false);
            $table->enum('link_type', ['none', 'debt', 'savings'])->default('none');
            $table->foreignId('linked_debt_id')->nullable()->constrained('debts')->nullOnDelete();
            $table->foreignId('linked_savings_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->uuid('series_id')->nullable();
            $table->enum('series_kind', ['installment', 'recurring'])->nullable();
            $table->unsignedSmallInteger('series_index')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['account_id', 'date']);
            $table->index(['user_id', 'category_id', 'date']);
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
