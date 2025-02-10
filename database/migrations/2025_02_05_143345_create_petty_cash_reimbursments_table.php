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
        Schema::create('petty_cash_reimbursments', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->text('supporting_documents')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->enum('status', ['submited', 'dep_approved', 'fin_approved', 'rembursed', 'fin_reject','dep_reject', 'draft'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_reimbursments');
    }
};
