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
        Schema::create('petty_cash_reimbursment_details', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('Vendor_id')->constrained('vendors');
            $table->foreignId('petty_cash_reimb_id')->constrained('petty_cash_reimbursments');
            $table->foreignId('sub_budget_id')->nullable()->constrained('sub_budgets');
            $table->string('details');
            $table->foreignId('po_id')->constrained('purchase_orders');
            $table->decimal('amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_reimbursment_details');
    }
};
