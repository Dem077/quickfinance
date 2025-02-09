<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('budget_transaction_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_budget_id')->constrained('sub_budget_accounts');
            $table->date('transaction_date');
            $table->string('transaction_type');
            $table->decimal('transaction_amount', 15, 2);
            $table->decimal('transaction_balance', 15, 2);
            $table->text('transaction_details')->nullable();
            $table->foreignId('transaction_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_transaction_histories');
    }
};
