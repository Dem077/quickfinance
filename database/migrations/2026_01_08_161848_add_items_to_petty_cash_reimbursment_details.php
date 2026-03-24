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
        Schema::table('petty_cash_reimbursment_details', function (Blueprint $table) {
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items')
                ->nullOnDelete()
                ->after('sub_budget_id');
        });
        Schema::table('purchase_order_details', function (Blueprint $table) {
            $table->foreignId('item_id')
                ->nullable()
                ->constrained('items')
                ->nullOnDelete()
                ->after('po_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_reimbursment_details', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            //
        });
        Schema::table('purchase_order_details', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            //
        });
    }
};
